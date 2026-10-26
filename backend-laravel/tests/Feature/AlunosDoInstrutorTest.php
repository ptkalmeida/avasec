<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\AdmissionRequest;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Models\User;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst/curso/{ref}/alunos` — a turma e a fila de matrículas.
 *
 * A aba do painel React era vazia com a aprovação automática ligada. O que
 * carrega o peso aqui: que a turma mostre SÓ o que o banco sabe (sem a presença
 * e o e-mail inventados do painel antigo), que a fila exista só quando a regra a
 * pede, e que um pedido de outro curso não se decida pelo endereço deste.
 */
final class AlunosDoInstrutorTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,name:string,token:string} */
    private function instrutor(string $nome): array
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id,
            'name' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'role' => 'instructor',
        ]);

        return ['id' => $id, 'name' => $nome, 'token' => Jwt::issue($id, $nome, 'instructor')];
    }

    private function curso(string $instructorId, string $titulo = 'Curso Da Turma Teste'): Course
    {
        $curso = Course::query()->create([
            'id' => 'course-turma-'.uniqid(),
            'title' => $titulo,
            'description' => 'Descrição longa o bastante para o validador aceitar.',
            'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png',
            'instructorId' => $instructorId,
            'instructorName' => 'Instrutor',
        ]);
        Course::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso->refresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{id:string,name:string,token:string}
     */
    private function aluno(string $nome, Course $curso, array $extra = []): array
    {
        $aluno = $this->makeStudent($nome);
        StudentEnrollment::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'enrolledCourseId' => $curso->id,
            'enrolledAt' => now(),
            'completedCourseIds' => [],
            'dropOutPenaltyUntil' => null,
            'canMultiEnroll' => false,
            'extraCourseIds' => [],
        ], $extra));

        return $aluno;
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst/curso/qualquer/alunos')->assertRedirect('/entrar');
    }

    public function test_mostra_a_turma_com_o_progresso_real(): void
    {
        $eu = $this->instrutor('Gestor Turma');
        $curso = $this->curso($eu['id']);
        $aulas = [];
        foreach ([1, 2, 3, 4] as $n) {
            $aulas[] = Lesson::query()->create([
                'id' => "lesson-turma-{$curso->id}-{$n}", 'courseId' => $curso->id,
                'title' => "Aula {$n}", 'duration' => '10 min', 'content' => 'c', 'lesson_order' => $n,
            ]);
        }
        $quiz = Quiz::query()->create(['id' => 'quiz-turma-'.uniqid(), 'courseId' => $curso->id, 'title' => 'Prova da turma']);
        $aluno = $this->aluno('Ana Da Turma', $curso);

        StudentProgress::query()->create([
            'id' => 'prog-'.uniqid(), 'userId' => $aluno['id'], 'studentName' => $aluno['name'], 'courseId' => $curso->id,
            // 2 aulas reais e UM id órfão: o resíduo não é progresso.
            'completedLessons' => [$aulas[0]->id, $aulas[1]->id, 'lesson-que-nao-existe-mais'],
            'attendedLiveSessions' => [],
        ]);
        QuizSubmission::query()->create([
            'id' => 'sub-turma-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $curso->id, 'quizId' => $quiz->id, 'scorePercent' => 90, 'passed' => true,
            'submittedAt' => '21/10/2026 às 10:00', 'enviadoEm' => now(),
        ]);

        $resposta = $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Ana Da Turma', escape: false);
        $resposta->assertSee('2 de 4', escape: false);   // aulas — o órfão não conta
        $resposta->assertSee('1 de 1', escape: false);   // avaliações aprovadas
        $resposta->assertSee('Ainda não', escape: false); // certificado
        $resposta->assertSee('1 aluno cursando', escape: false);
    }

    /** Uma tentativa aprovada basta, como o certificado diz. */
    public function test_aprovado_uma_vez_continua_aprovado_mesmo_se_a_ultima_foi_pior(): void
    {
        $eu = $this->instrutor('Gestor Aprovado Uma Vez');
        $curso = $this->curso($eu['id']);
        $quiz = Quiz::query()->create(['id' => 'quiz-uma-vez-'.uniqid(), 'courseId' => $curso->id, 'title' => 'Prova']);
        $aluno = $this->aluno('Aluno Aprovou E Piorou', $curso);
        foreach ([[100, true, 0], [0, false, 5]] as $n => [$nota, $passou, $seg]) {
            QuizSubmission::query()->create([
                'id' => 'sub-uv-'.$n.'-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
                'courseId' => $curso->id, 'quizId' => $quiz->id, 'scorePercent' => $nota, 'passed' => $passou,
                'submittedAt' => '21/10/2026 às 10:0'.$n, 'enviadoEm' => now()->addSeconds($seg),
            ]);
        }

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('1 de 1', escape: false);
    }

    public function test_certificado_emitido_aparece(): void
    {
        $eu = $this->instrutor('Gestor Certificado');
        $curso = $this->curso($eu['id']);
        $aluno = $this->aluno('Aluno Certificado', $curso);
        Certificate::query()->create([
            'id' => 'cert-turma-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $curso->id, 'courseTitle' => $curso->title, 'issueDate' => now(),
            'attendancePercent' => 90, 'verificationHash' => 'AVA-TURMA-'.strtoupper(uniqid()),
        ]);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('Emitido', escape: false);
    }

    /** A matrícula concorrente conta: foi o defeito que deixou a caixa de mensagens vazia. */
    public function test_aluno_por_matricula_concorrente_aparece(): void
    {
        $eu = $this->instrutor('Gestor Concorrente Turma');
        $curso = $this->curso($eu['id']);
        $this->aluno('Aluno Concorrente Da Turma', $curso, ['enrolledCourseId' => null, 'extraCourseIds' => [$curso->id]]);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('Aluno Concorrente Da Turma', escape: false);
    }

    /** Quem concluiu entra só na CONTAGEM: ex-aluno não fica no alcance para sempre. */
    public function test_quem_concluiu_entra_so_na_contagem_e_sem_nome(): void
    {
        $eu = $this->instrutor('Gestor Concluidos');
        $curso = $this->curso($eu['id']);
        $this->aluno('Aluno Que Ja Concluiu', $curso, ['enrolledCourseId' => null, 'completedCourseIds' => [$curso->id]]);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('1 já concluiu', escape: false)
            ->assertDontSee('Aluno Que Ja Concluiu', escape: false);
    }

    public function test_aluno_de_outro_curso_nao_aparece(): void
    {
        $eu = $this->instrutor('Gestor Turma Um');
        $meu = $this->curso($eu['id'], 'Curso Meu Da Turma');
        $outro = $this->instrutor('Gestor Turma Dois');
        $alheio = $this->curso($outro['id'], 'Curso Alheio Da Turma');
        $this->aluno('Aluno Do Curso Alheio', $alheio);

        $this->como($eu['token'])->get('/inst/curso/'.$meu->slug.'/alunos')
            ->assertDontSee('Aluno Do Curso Alheio', escape: false)
            ->assertSee('Ninguém está cursando este curso agora.', escape: false);
    }

    public function test_curso_de_outro_instrutor_responde_404(): void
    {
        $eu = $this->instrutor('Gestor Nega Um');
        $outro = $this->instrutor('Gestor Nega Dois');
        $alheio = $this->curso($outro['id'], 'Curso Negado Da Turma');

        $this->como($eu['token'])->get('/inst/curso/'.$alheio->slug.'/alunos')->assertNotFound();
        $this->como($eu['token'])->post('/inst/curso/'.$alheio->slug.'/pedidos/qualquer', ['decisao' => 'approved'])->assertNotFound();
    }

    /** O que o painel antigo inventava não volta. */
    public function test_nao_mostra_presenca_nem_selo_de_qualificado(): void
    {
        $eu = $this->instrutor('Gestor Sem Presenca Turma');
        $curso = $this->curso($eu['id']);
        $this->aluno('Aluno Sem Presenca Inventada', $curso);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertDontSee('Presença', escape: false)
            ->assertDontSee('Qualificado', escape: false)
            ->assertDontSee('@escola.dev.br', escape: false);
    }

    public function test_com_aprovacao_automatica_nao_ha_fila_e_a_tela_diz_por_que(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => true]);
        $eu = $this->instrutor('Gestor Automatica');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('aprovadas automaticamente', escape: false)
            ->assertDontSee('Pedidos de matrícula', escape: false);
    }

    public function test_sem_aprovacao_automatica_a_fila_lista_so_os_pedidos_deste_curso(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Fila');
        $curso = $this->curso($eu['id']);
        $outro = $this->curso($eu['id'], 'Curso Irmao Da Fila');
        $aluno = $this->makeStudent('Aluno Que Pediu');
        $outroAluno = $this->makeStudent('Aluno Que Pediu Outro Curso');
        foreach ([[$aluno, $curso], [$outroAluno, $outro]] as $i => [$a, $c]) {
            AdmissionRequest::query()->create([
                'id' => 'adm-fila-'.$i.'-'.uniqid(), 'studentName' => $a['name'], 'userId' => $a['id'],
                'courseId' => $c->id, 'status' => 'pending', 'submittedAt' => '21/10/2026',
            ]);
        }

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('Pedidos de matrícula', escape: false)
            ->assertSee('Aluno Que Pediu', escape: false)
            ->assertDontSee('Aluno Que Pediu Outro Curso', escape: false);
    }

    public function test_aprovar_um_pedido_matricula_o_aluno_e_audita(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Aprova');
        $curso = $this->curso($eu['id']);
        $aluno = $this->makeStudent('Aluno A Aprovar');
        $pedido = AdmissionRequest::query()->create([
            'id' => 'adm-aprova-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $curso->id, 'status' => 'pending', 'submittedAt' => '21/10/2026',
        ]);
        $antes = (int) DB::table('SecurityLog')->count();

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/pedidos/'.$pedido->id, ['decisao' => 'approved'])
            ->assertRedirect('/inst/curso/'.$curso->slug.'/alunos');

        $this->assertSame('approved', AdmissionRequest::query()->whereKey($pedido->id)->value('status'));
        $this->assertSame($curso->id, StudentEnrollment::query()->whereKey($aluno['id'])->value('enrolledCourseId'));
        $this->assertGreaterThan($antes, (int) DB::table('SecurityLog')->count());
    }

    public function test_reprovar_nao_matricula(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Reprova');
        $curso = $this->curso($eu['id']);
        $aluno = $this->makeStudent('Aluno A Reprovar');
        $pedido = AdmissionRequest::query()->create([
            'id' => 'adm-reprova-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $curso->id, 'status' => 'pending', 'submittedAt' => '21/10/2026',
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/pedidos/'.$pedido->id, ['decisao' => 'rejected']);

        $this->assertSame('rejected', AdmissionRequest::query()->whereKey($pedido->id)->value('status'));
        $this->assertNull(StudentEnrollment::query()->whereKey($aluno['id'])->value('enrolledCourseId'));
    }

    /** O pedido de OUTRO curso não se decide pelo endereço deste. */
    public function test_pedido_de_outro_curso_nao_se_decide_por_este_endereco(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Pedido Alheio');
        $meu = $this->curso($eu['id'], 'Curso Meu Pedido');
        $outro = $this->curso($eu['id'], 'Curso Irmao Pedido');
        $aluno = $this->makeStudent('Aluno Do Pedido Irmao');
        $pedido = AdmissionRequest::query()->create([
            'id' => 'adm-irmao-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $outro->id, 'status' => 'pending', 'submittedAt' => '21/10/2026',
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$meu->slug.'/pedidos/'.$pedido->id, ['decisao' => 'approved'])
            ->assertNotFound();

        $this->assertSame('pending', AdmissionRequest::query()->whereKey($pedido->id)->value('status'));
    }

    /** Decidir um pedido já decidido não é possível: ele sai da fila. */
    public function test_pedido_ja_decidido_nao_se_decide_de_novo(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Ja Decidido');
        $curso = $this->curso($eu['id']);
        $aluno = $this->makeStudent('Aluno Ja Decidido');
        $pedido = AdmissionRequest::query()->create([
            'id' => 'adm-decidido-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $curso->id, 'status' => 'rejected', 'submittedAt' => '21/10/2026',
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/pedidos/'.$pedido->id, ['decisao' => 'approved'])
            ->assertNotFound();

        $this->assertSame('rejected', AdmissionRequest::query()->whereKey($pedido->id)->value('status'));
    }

    public function test_decisao_invalida_e_recusada(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Decisao Invalida');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/alunos')
            ->post('/inst/curso/'.$curso->slug.'/pedidos/x', ['decisao' => 'talvez'])
            ->assertSessionHasErrors('decisao');
    }

    public function test_sem_dados_gerenciais_a_tela_responde_404(): void
    {
        config(['features.dadosGerenciais' => false]);
        $eu = $this->instrutor('Gestor Sem Dados');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')->assertNotFound();
    }
}
