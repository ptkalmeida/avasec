<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Models\User;
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

    private function curso(string $titulo, int $status = Visibilidade::PUBLICADO): Course
    {
        $curso = Course::query()->create([
            'id' => 'course-adm-'.uniqid(),
            'title' => $titulo,
            'description' => 'd',
            'category' => 'Teste Admin',
            'thumbnail' => 'sem-imagem.png',
            'instructorName' => 'Instrutor',
        ]);
        Course::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => $status,
        ]);

        return $curso->refresh();
    }

    /**
     * @param  list<string>  $extras
     * @param  list<string>  $concluidos
     */
    private function matricular(string $userId, string $nome, ?string $principal, array $extras = [], array $concluidos = []): void
    {
        StudentEnrollment::query()->updateOrCreate(['userId' => $userId], [
            'id' => (string) Str::uuid(),
            'studentName' => $nome,
            'enrolledCourseId' => $principal,
            'enrolledAt' => $principal === null ? null : now()->toIso8601String(),
            'completedCourseIds' => $concluidos,
            'extraCourseIds' => $extras,
            'canMultiEnroll' => $extras !== [],
        ]);
    }

    public function test_visitante_vai_para_o_login_e_instrutor_para_a_area_dele(): void
    {
        $this->get('/admin')->assertRedirect('/entrar');
        $this->get('/admin/relatorio/alunos')->assertRedirect('/entrar');

        $instrutor = DB::table('User')->where('role', 'instructor')->where('status', 'active')->first(['id', 'name']);
        $this->assertNotNull($instrutor);
        $token = Jwt::issue($instrutor->id, $instrutor->name, 'instructor');

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

        $this->assertSame(User::query()->where('role', 'student')->count(), $resumo['alunos']);
        $this->assertSame(Course::query()->count(), $resumo['cursos']);
        $this->assertSame(Course::query()->where('status', Visibilidade::PUBLICADO)->count(), $resumo['cursosPublicados']);
        $this->assertSame(User::query()->where('role', 'instructor')->count(), $resumo['equipe']);

        $tela = $this->como($this->admin())->get('/admin');
        $tela->assertSee('Alunos cadastrados');
        $tela->assertSee('Matrículas ativas');
        $tela->assertSee('Certificados emitidos');
    }

    /**
     * O React olhava só o curso principal: quem estava matriculada só num curso
     * concorrente aparecia com zero. A contagem agora é a do painel do
     * instrutor — principal mais concorrentes — e é a que o banco real exige
     * (uma aluna existente está só em `extraCourseIds`).
     */
    public function test_matricula_conta_o_curso_principal_e_os_concorrentes(): void
    {
        $servico = app(RelatoriosDoAdmin::class);
        $antes = $servico->visaoGeral()['matriculas'];

        $aluno = $this->makeStudent('Aluno Matriculas');
        $a = $this->curso('Curso A Admin');
        $b = $this->curso('Curso B Admin');
        $c = $this->curso('Curso C Admin');

        $this->matricular($aluno['id'], $aluno['name'], $a->id, [$b->id, $c->id]);

        $this->assertSame($antes + 3, $servico->visaoGeral()['matriculas']);

        // Só concorrente, sem principal: ainda conta.
        $outro = $this->makeStudent('Aluno So Concorrente');
        $this->matricular($outro['id'], $outro['name'], null, [$a->id]);

        $this->assertSame($antes + 4, $servico->visaoGeral()['matriculas']);
    }

    public function test_relatorio_de_cursos_traz_matriculados_concluintes_e_progresso(): void
    {
        $curso = $this->curso('Curso Com Numeros');
        foreach (['aula-1', 'aula-2', 'aula-3', 'aula-4'] as $n) {
            Lesson::query()->create([
                'id' => $curso->id.'-'.$n, 'courseId' => $curso->id, 'title' => $n,
                'content' => 'texto', 'duration' => '10', 'lesson_order' => 1,
            ]);
        }

        $aluno = $this->makeStudent('Aluno Numeros');
        $this->matricular($aluno['id'], $aluno['name'], $curso->id);
        StudentProgress::query()->create([
            'id' => (string) Str::uuid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $curso->id,
            // Duas válidas e uma que não existe mais no curso: esta não conta.
            'completedLessons' => [$curso->id.'-aula-1', $curso->id.'-aula-2', 'aula-que-sumiu'],
            'attendedLiveSessions' => [],
        ]);

        $linha = collect(app(RelatoriosDoAdmin::class)->cursos())->firstWhere('titulo', 'Curso Com Numeros');

        $this->assertNotNull($linha);
        $this->assertSame(4, $linha['aulas']);
        $this->assertSame(1, $linha['matriculados']);
        $this->assertSame(50, $linha['progresso']);
        $this->assertSame(0, $linha['concluintes']);

        // Concluinte vem da coluna que registra a conclusão.
        $this->matricular($aluno['id'], $aluno['name'], null, [], [$curso->id]);
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

        User::query()->findOrFail($aluno['id'])->inativar($aluno['id'], 'teste');

        $this->assertSame($antes - 1, $servico->visaoGeral()['alunos']);
    }

    public function test_a_lista_de_matriculas_mostra_aluno_curso_e_certificado_pendente(): void
    {
        $curso = $this->curso('Curso Lista Matriculas');
        $aluno = $this->makeStudent('Aluno Lista Matriculas');
        $this->matricular($aluno['id'], $aluno['name'], $curso->id);

        $resultado = app(RelatoriosDoAdmin::class)->matriculas(1);
        $mostradas = $resultado['linhas'];

        // Pode haver mais de uma página no banco de desenvolvimento: confere o
        // total, e a presença só se a linha cair na primeira página.
        $this->assertGreaterThanOrEqual(1, $resultado['total']);
        $dele = collect($mostradas)->firstWhere('aluno', $aluno['name']);
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
