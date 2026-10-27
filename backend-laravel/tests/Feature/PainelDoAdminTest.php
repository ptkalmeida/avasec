<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\Usuario;
use App\Services\RelatoriosDoAdmin;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Dashboard e relatórios do painel administrativo (fatia 2 do M5).
 *
 * O banco de desenvolvimento tem dados reais, e cada teste roda numa
 * transação. Por isso os números são conferidos como DIFERENÇA (antes e
 * depois de criar um caso) e contra a consulta direta, nunca contra um valor
 * escrito no teste.
 */
final class PainelDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function curso(string $titulo, int $status = Visibilidade::PUBLICADO): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-adm-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'd',
            'categoria' => 'Teste Admin',
            'miniatura' => 'sem-imagem.png',
            'nome_instrutor' => 'Instrutor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => $status,
        ]);

        return $curso->refresh();
    }

    /**
     * @param  list<string>  $extras
     * @param  list<string>  $concluidos
     */
    private function matricular(string $usuario_id, string $nome, ?string $principal, array $extras = [], array $concluidos = []): void
    {
        Matricula::query()->updateOrCreate(['usuario_id' => $usuario_id], [
            'id' => (string) Str::uuid(),
            'nome_aluno' => $nome,
            'curso_matriculado_id' => $principal,
            'matriculado_em_texto' => $principal === null ? null : now()->toIso8601String(),
            'cursos_concluidos_ids' => $concluidos,
            'cursos_extras_ids' => $extras,
            'pode_multimatricula' => $extras !== [],
        ]);
    }

    public function test_visitante_vai_para_o_login_e_instrutor_para_a_area_dele(): void
    {
        $this->get('/admin')->assertRedirect('/entrar');
        $this->get('/admin/relatorio/alunos')->assertRedirect('/entrar');

        $instrutor = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->first(['id', 'nome']);
        $this->assertNotNull($instrutor);
        $token = Jwt::issue($instrutor->id, $instrutor->nome, 'instructor');

        $this->como($token)->get('/admin')->assertRedirect('/inst');
    }

    public function test_as_cinco_abas_abrem_no_servidor(): void
    {
        $token = $this->admin();

        foreach (['/admin', '/admin/relatorio/alunos', '/admin/relatorio/professores', '/admin/relatorio/cursos', '/admin/relatorio/inscricoes'] as $caminho) {
            $resposta = $this->como($token)->get($caminho);
            $resposta->assertOk();
            $resposta->assertDontSee('<div id="root">', false);
            $resposta->assertSee('Imprimir relatório');
            $resposta->assertSee('Visão Geral');
            $resposta->assertSee('Matrículas');
        }
    }

    public function test_a_aba_atual_e_a_unica_marcada(): void
    {
        $resposta = $this->como($this->admin())->get('/admin/relatorio/cursos');

        $resposta->assertSee('Relatório: Cursos');
        $this->assertSame(1, substr_count($resposta->getContent(), 'aria-current="page"') - 1, 'uma aba + o item da sidebar');
    }

    public function test_aba_inventada_e_a_visao_geral_com_dois_enderecos_voltam_para_a_entrada(): void
    {
        $token = $this->admin();

        $this->como($token)->get('/admin/relatorio/consolidado')->assertRedirect('/admin');
        $this->como($token)->get('/admin/relatorio/inventada')->assertRedirect('/admin');
    }

    public function test_pagina_alem_do_fim_volta_para_a_ultima(): void
    {
        $this->como($this->admin())->get('/admin/relatorio/alunos?pagina=9999')
            ->assertRedirect('/admin/relatorio/alunos?pagina=1');
    }

    public function test_os_indicadores_batem_com_o_banco(): void
    {
        $resumo = app(RelatoriosDoAdmin::class)->visaoGeral();

        $this->assertSame(Usuario::query()->where('papel', 'student')->count(), $resumo['alunos']);
        $this->assertSame(Curso::query()->count(), $resumo['cursos']);
        $this->assertSame(Curso::query()->where('status', Visibilidade::PUBLICADO)->count(), $resumo['cursosPublicados']);
        $this->assertSame(Usuario::query()->where('papel', 'instructor')->count(), $resumo['equipe']);

        $tela = $this->como($this->admin())->get('/admin');
        $tela->assertSee('Alunos cadastrados');
        $tela->assertSee('Matrículas ativas');
        $tela->assertSee('Certificados emitidos');
    }

    /**
     * O React olhava só o curso principal: quem estava matriculada só num curso
     * concorrente aparecia com zero. A contagem agora é a do painel do
     * instrutor — principal mais concorrentes — e é a que o banco real exige
     * (uma aluna existente está só em `cursos_extras_ids`).
     */
    public function test_matricula_conta_o_curso_principal_e_os_concorrentes(): void
    {
        $servico = app(RelatoriosDoAdmin::class);
        $antes = $servico->visaoGeral()['matriculas'];

        $aluno = $this->makeStudent('Aluno Matriculas');
        $a = $this->curso('Curso A Admin');
        $b = $this->curso('Curso B Admin');
        $c = $this->curso('Curso C Admin');

        $this->matricular($aluno['id'], $aluno['nome'], $a->id, [$b->id, $c->id]);

        $this->assertSame($antes + 3, $servico->visaoGeral()['matriculas']);

        // Só concorrente, sem principal: ainda conta.
        $outro = $this->makeStudent('Aluno So Concorrente');
        $this->matricular($outro['id'], $outro['nome'], null, [$a->id]);

        $this->assertSame($antes + 4, $servico->visaoGeral()['matriculas']);
    }

    public function test_relatorio_de_cursos_traz_matriculados_concluintes_e_progresso(): void
    {
        $curso = $this->curso('Curso Com Numeros');
        foreach (['aula-1', 'aula-2', 'aula-3', 'aula-4'] as $n) {
            Aula::query()->create([
                'id' => $curso->id.'-'.$n, 'curso_id' => $curso->id, 'titulo' => $n,
                'conteudo' => 'texto', 'duracao' => '10', 'ordem' => 1,
            ]);
        }

        $aluno = $this->makeStudent('Aluno Numeros');
        $this->matricular($aluno['id'], $aluno['nome'], $curso->id);
        ProgressoAluno::query()->create([
            'id' => (string) Str::uuid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id,
            // Duas válidas e uma que não existe mais no curso: esta não conta.
            'aulas_concluidas' => [$curso->id.'-aula-1', $curso->id.'-aula-2', 'aula-que-sumiu'],
            'sessoes_ao_vivo_assistidas' => [],
        ]);

        $linha = collect(app(RelatoriosDoAdmin::class)->cursos())->firstWhere('titulo', 'Curso Com Numeros');

        $this->assertNotNull($linha);
        $this->assertSame(4, $linha['aulas']);
        $this->assertSame(1, $linha['matriculados']);
        $this->assertSame(50, $linha['progresso']);
        $this->assertSame(0, $linha['concluintes']);

        // Concluinte vem da coluna que registra a conclusão.
        $this->matricular($aluno['id'], $aluno['nome'], null, [], [$curso->id]);
        $depois = collect(app(RelatoriosDoAdmin::class)->cursos())->firstWhere('titulo', 'Curso Com Numeros');
        $this->assertSame(1, $depois['concluintes']);
        $this->assertSame(0, $depois['matriculados']);
    }

    public function test_curso_sem_aulas_ou_em_rascunho_aparece_com_a_visibilidade_real(): void
    {
        $this->curso('Curso Em Rascunho Admin', Visibilidade::RASCUNHO);

        $tela = $this->como($this->admin())->get('/admin/relatorio/cursos');

        $tela->assertSee('Curso Em Rascunho Admin');
        $tela->assertSee('Rascunho');
    }

    public function test_aluno_inativado_nao_entra_nas_contagens_de_cadastro(): void
    {
        $servico = app(RelatoriosDoAdmin::class);
        $aluno = $this->makeStudent('Aluno A Inativar');
        $antes = $servico->visaoGeral()['alunos'];

        Usuario::query()->findOrFail($aluno['id'])->inativar($aluno['id'], 'teste');

        $this->assertSame($antes - 1, $servico->visaoGeral()['alunos']);
    }

    public function test_a_lista_de_matriculas_mostra_aluno_curso_e_certificado_pendente(): void
    {
        $curso = $this->curso('Curso Lista Matriculas');
        $aluno = $this->makeStudent('Aluno Lista Matriculas');
        $this->matricular($aluno['id'], $aluno['nome'], $curso->id);

        $resultado = app(RelatoriosDoAdmin::class)->matriculas(1);
        $mostradas = $resultado['linhas'];

        // Pode haver mais de uma página no banco de desenvolvimento: confere o
        // total, e a presença só se a linha cair na primeira página.
        $this->assertGreaterThanOrEqual(1, $resultado['total']);
        $dele = collect($mostradas)->firstWhere('aluno', $aluno['nome']);
        if ($dele !== null) {
            $this->assertSame('Curso Lista Matriculas', $dele['curso']);
            $this->assertFalse($dele['certificado']);
        }
    }

    public function test_nada_do_que_era_inventado_no_navegador_volta(): void
    {
        $token = $this->admin();

        foreach (['/admin/relatorio/alunos', '/admin/relatorio/inscricoes'] as $caminho) {
            $tela = $this->como($token)->get($caminho);
            $tela->assertDontSee('Sem acesso');
            $tela->assertDontSee('Último acesso');
            $tela->assertDontSee('Emitir...');
        }
    }
}
