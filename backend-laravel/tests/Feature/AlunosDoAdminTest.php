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
use App\Services\AlunosDoAdmin;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Alunos" do painel administrativo — lista e ficha (fatia 5 do M5, parte de
 * leitura). O banco de desenvolvimento tem dados reais, então cada caso isola o
 * que mede por um nome único na busca.
 */
final class AlunosDoAdminTest extends TestCase
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

    private function curso(string $titulo): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-aadm-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição do curso para teste.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'nome_instrutor' => 'Gestor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso->refresh();
    }

    /**
     * @param  list<string>  $extras
     * @param  list<string>  $concluidos
     */
    private function matricular(string $usuario_id, string $nome, ?string $principal, array $extras = [], array $concluidos = [], bool $multipla = false): void
    {
        Matricula::query()->updateOrCreate(['usuario_id' => $usuario_id], [
            'id' => (string) Str::uuid(), 'nome_aluno' => $nome, 'curso_matriculado_id' => $principal,
            'cursos_concluidos_ids' => $concluidos, 'cursos_extras_ids' => $extras, 'pode_multimatricula' => $multipla,
        ]);
    }

    public function test_so_o_admin_chega_a_lista_e_a_ficha(): void
    {
        $aluno = $this->makeStudent('Aluno Acesso Lista');

        $this->get('/admin/alunos')->assertRedirect('/entrar');
        $this->get('/admin/alunos/'.$aluno['id'])->assertRedirect('/entrar');
        $this->como($aluno['token'])->get('/admin/alunos')->assertRedirect('/aluno');
    }

    public function test_a_lista_mostra_nome_email_matricula_e_progresso_reais(): void
    {
        $curso = $this->curso('Curso Da Lista De Alunos');
        foreach (['a1', 'a2', 'a3', 'a4'] as $n) {
            Aula::query()->create(['id' => $curso->id.'-'.$n, 'curso_id' => $curso->id, 'titulo' => $n, 'conteudo' => 'x', 'duracao' => '10', 'ordem' => 1]);
        }
        $aluno = $this->makeStudent('Zenaide Listada Unica');
        $this->matricular($aluno['id'], $aluno['nome'], $curso->id);
        ProgressoAluno::query()->create([
            'id' => (string) Str::uuid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $curso->id,
            'aulas_concluidas' => [$curso->id.'-a1'], 'sessoes_ao_vivo_assistidas' => [],
        ]);

        $tela = $this->como($this->admin())->get('/admin/alunos?busca=Zenaide+Listada');

        $tela->assertOk();
        $tela->assertDontSee('<div id="root">', false);
        $tela->assertSee('Zenaide Listada Unica');
        $tela->assertSee('Curso Da Lista De Alunos');
        $tela->assertSee('25%');
        $tela->assertSee('Ativa');
        $tela->assertSee('/admin/alunos/'.$aluno['id'], false);
    }

    public function test_aluno_sem_matricula_diz_que_nao_ha(): void
    {
        $aluno = $this->makeStudent('Wanderleia Sem Vinculo');

        $this->como($this->admin())->get('/admin/alunos?busca=Wanderleia+Sem+Vinculo')
            ->assertSee('Wanderleia Sem Vinculo')
            ->assertSee('Sem matrícula');
        $this->assertNotNull($aluno['id']);
    }

    public function test_filtros_de_matricula_curso_e_conta(): void
    {
        $curso = $this->curso('Curso Do Filtro Alunos');
        $com = $this->makeStudent('Filtrada Com Matricula Qzk');
        $sem = $this->makeStudent('Filtrada Sem Matricula Qzk');
        $extra = $this->makeStudent('Filtrada Concorrente Qzk');
        $this->matricular($com['id'], $com['nome'], $curso->id);
        $this->matricular($extra['id'], $extra['nome'], null, [$curso->id]);
        Usuario::query()->whereKey($sem['id'])->update(['status' => 'blocked']);
        $token = $this->admin();

        $comMatricula = $this->como($token)->get('/admin/alunos?busca=Qzk&matricula=com');
        $comMatricula->assertSee('Filtrada Com Matricula Qzk');
        $comMatricula->assertSee('Filtrada Concorrente Qzk');
        $comMatricula->assertDontSee('Filtrada Sem Matricula Qzk');

        $semMatricula = $this->como($token)->get('/admin/alunos?busca=Qzk&matricula=sem');
        $semMatricula->assertSee('Filtrada Sem Matricula Qzk');
        $semMatricula->assertDontSee('Filtrada Com Matricula Qzk');

        // O curso casa pelo principal OU por concorrente.
        $doCurso = $this->como($token)->get('/admin/alunos?busca=Qzk&curso='.$curso->id);
        $doCurso->assertSee('Filtrada Com Matricula Qzk');
        $doCurso->assertSee('Filtrada Concorrente Qzk');
        $doCurso->assertDontSee('Filtrada Sem Matricula Qzk');

        $bloqueados = $this->como($token)->get('/admin/alunos?busca=Qzk&conta=blocked');
        $bloqueados->assertSee('Filtrada Sem Matricula Qzk');
        $bloqueados->assertSee('Bloqueada');
        $bloqueados->assertDontSee('Filtrada Com Matricula Qzk');
    }

    public function test_filtro_de_quem_ja_concluiu_curso(): void
    {
        $curso = $this->curso('Curso Concluido Filtro');
        $aluno = $this->makeStudent('Concluinte Do Filtro Qzj');
        $outro = $this->makeStudent('Nao Concluinte Do Filtro Qzj');
        $this->matricular($aluno['id'], $aluno['nome'], null, [], [$curso->id]);
        $this->matricular($outro['id'], $outro['nome'], null);

        $tela = $this->como($this->admin())->get('/admin/alunos?busca=Qzj&matricula=concluiu');

        $tela->assertSee('Concluinte Do Filtro Qzj');
        $tela->assertDontSee('Nao Concluinte Do Filtro Qzj');
        $tela->assertSee('1 curso concluído');
    }

    public function test_a_busca_trata_curinga_como_texto_e_acha_pelo_email(): void
    {
        $aluno = $this->makeStudent('Busca Curinga Alunos');
        $email = (string) Usuario::query()->whereKey($aluno['id'])->value('email');
        $token = $this->admin();

        $this->como($token)->get('/admin/alunos?busca='.urlencode($email))->assertSee('Busca Curinga Alunos');
        $this->como($token)->get('/admin/alunos?busca=%25')->assertDontSee('Busca Curinga Alunos');
    }

    public function test_ordenacao_por_nome(): void
    {
        $this->makeStudent('Aaa Ordem Qzo');
        $this->makeStudent('Zzz Ordem Qzo');
        $token = $this->admin();

        $asc = $this->como($token)->get('/admin/alunos?busca=Ordem+Qzo&ordem=nome')->getContent();
        $desc = $this->como($token)->get('/admin/alunos?busca=Ordem+Qzo&ordem=nome-desc')->getContent();

        $this->assertLessThan(strpos((string) $asc, 'Zzz Ordem Qzo'), strpos((string) $asc, 'Aaa Ordem Qzo'));
        $this->assertGreaterThan(strpos((string) $desc, 'Zzz Ordem Qzo'), strpos((string) $desc, 'Aaa Ordem Qzo'));
    }

    public function test_os_cartoes_somam(): void
    {
        $resumo = app(AlunosDoAdmin::class)->resumo();

        $this->assertSame(Usuario::query()->where('papel', 'student')->count(), $resumo['total']);
        $this->assertSame($resumo['total'], $resumo['comMatricula'] + $resumo['semMatricula']);
    }

    /**
     * O retirado sai da lista dos ATIVOS e a ficha dele responde 404.
     *
     * Até 26/10/2026 esta asserção era `assertDontSee` na página inteira — porque o que
     * saía do ar não aparecia em tela nenhuma (o "limbo"). Agora ele aparece, de propósito,
     * na seção "Alunos retirados da plataforma", separada dos ativos. A regra que o teste
     * protege não mudou — o retirado não é um aluno ativo —; ela passou a ser dita onde
     * vale: ausente da parte dos ativos e presente na dos retirados (`RetiradosDoAdminTest`
     * cobre a reativação).
     */
    public function test_aluno_inativado_some_da_lista_e_a_ficha_responde_404(): void
    {
        $aluno = $this->makeStudent('Inativado Da Lista Qzp');
        Usuario::query()->findOrFail($aluno['id'])->inativar($aluno['id'], 'teste');

        $html = $this->como($this->admin())->get('/admin/alunos?busca=Qzp')->getContent();
        $this->assertIsString($html);
        $secao = strpos($html, 'Alunos retirados da plataforma (');
        $this->assertNotFalse($secao, 'A seção de retirados precisa existir.');

        $this->assertStringNotContainsString('Inativado Da Lista Qzp', substr($html, 0, $secao), 'O retirado não pode estar entre os ativos.');
        $this->assertStringContainsString('Inativado Da Lista Qzp', substr($html, $secao), 'O retirado precisa estar na seção de retirados.');
        $this->como($this->admin())->get('/admin/alunos/'.$aluno['id'])->assertNotFound();
    }

    public function test_a_ficha_mostra_dados_matriculas_progresso_e_certificados(): void
    {
        $principal = $this->curso('Curso Principal Da Ficha');
        $extra = $this->curso('Curso Extra Da Ficha');
        $feito = $this->curso('Curso Feito Da Ficha');
        foreach (['b1', 'b2'] as $n) {
            Aula::query()->create(['id' => $principal->id.'-'.$n, 'curso_id' => $principal->id, 'titulo' => $n, 'conteudo' => 'x', 'duracao' => '10', 'ordem' => 1]);
        }

        $aluno = $this->makeStudent('Ficha Completa Qzf');
        Usuario::query()->whereKey($aluno['id'])->update(['municipio' => 'Niterói', 'uf' => 'RJ', 'area_interesse' => 'Audiovisual']);
        $this->matricular($aluno['id'], $aluno['nome'], $principal->id, [$extra->id], [$feito->id], true);
        ProgressoAluno::query()->create([
            'id' => (string) Str::uuid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $principal->id,
            'aulas_concluidas' => [$principal->id.'-b1', $principal->id.'-b2'], 'sessoes_ao_vivo_assistidas' => [],
        ]);
        Certificado::query()->create([
            'id' => 'cert-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $feito->id,
            'titulo_curso' => 'Curso Feito Da Ficha', 'data_emissao' => '2026-08-01', 'percentual_frequencia' => 100, 'hash_verificacao' => Str::random(32),
        ]);

        $tela = $this->como($this->admin())->get('/admin/alunos/'.$aluno['id']);

        $tela->assertOk();
        $tela->assertDontSee('<div id="root">', false);
        $tela->assertSee('Ficha Completa Qzf');
        $tela->assertSee('Niterói / RJ');
        $tela->assertSee('Audiovisual');
        $tela->assertSee('Curso Principal Da Ficha');
        $tela->assertSee('Principal');
        $tela->assertSee('Curso Extra Da Ficha');
        $tela->assertSee('Concorrente');
        $tela->assertSee('2 de 2');
        $tela->assertSee('100%');
        $tela->assertSee('Matrícula múltipla permitida');
        $tela->assertSee('Cursos concluídos');
        $tela->assertSee('Curso Feito Da Ficha');
        $tela->assertSee('Certificados emitidos (1)');
    }

    public function test_a_ficha_mascara_o_cpf_e_nao_mostra_o_hash(): void
    {
        $aluno = $this->makeStudent('Ficha Cpf Qzc');

        $tela = $this->como($this->admin())->get('/admin/alunos/'.$aluno['id']);

        $tela->assertDontSee($aluno['cpf']);
        $tela->assertSee(substr($aluno['cpf'], 0, 3).'.***.***-'.substr($aluno['cpf'], 9, 2));
        $tela->assertDontSee('senha_hash');
    }

    public function test_ficha_de_instrutor_ou_inexistente_responde_404(): void
    {
        $gestor = Usuario::query()->where('papel', 'instructor')->firstOrFail();

        $this->como($this->admin())->get('/admin/alunos/'.$gestor->id)->assertNotFound();
        $this->como($this->admin())->get('/admin/alunos/nao-existe')->assertNotFound();
        $this->assertNotNull(Jwt::class);
    }

    public function test_nada_do_que_era_simulado_no_navegador_volta(): void
    {
        $aluno = $this->makeStudent('Sem Simulacao Qzs');
        $token = $this->admin();

        foreach (['/admin/alunos?busca=Qzs', '/admin/alunos/'.$aluno['id']] as $caminho) {
            $tela = $this->como($token)->get($caminho);
            $tela->assertDontSee('Risco de Evasão');
            $tela->assertDontSee('Ausentes');
            $tela->assertDontSee('Último acesso');
            $tela->assertDontSee('Link Mágico');
            $tela->assertDontSee('magic_token');
            $tela->assertDontSee('Turma');
            $tela->assertDontSee('Polo');
            $tela->assertDontSee('RA:');
        }
    }
}
