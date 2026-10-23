<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\ForumMessage;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\PracticalExercise;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Achados da varredura de segurança de 23/10/2026 que não cabem num arquivo de
 * domínio só. Cada teste descreve o ataque que deixou de funcionar.
 */
final class VarreduraDeSegurancaTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** @return array{id:string,token:string,curso:Course} */
    private function instrutorComCurso(string $nome): array
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id, 'name' => $nome, 'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT), 'role' => 'instructor',
        ]);
        $curso = Course::query()->create([
            'id' => 'course-seg-'.uniqid(), 'title' => 'Curso '.$nome,
            'description' => 'Descrição longa o bastante para o validador aceitar.', 'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png', 'instructorId' => $id, 'instructorName' => $nome,
        ]);

        return ['id' => $id, 'token' => Jwt::issue($id, $nome, 'instructor'), 'curso' => $curso];
    }

    public function test_instrutor_nao_marca_curso_como_concluido_nem_mexe_na_penalidade(): void
    {
        $eu = $this->instrutorComCurso('Instrutor Seguranca Um');
        $aluno = $this->makeStudent('Aluno Seguranca Concluido');

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->putJson('/api/enrollments/'.$aluno['id'], [
                'enrolledCourseId' => $eu['curso']->id,
                'completedCourseIds' => [$this->anySeededCourseId()],
            ])->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->putJson('/api/enrollments/'.$aluno['id'], [
                'enrolledCourseId' => $eu['curso']->id,
                'dropOutPenaltyUntil' => '2099-01-01',
            ])->assertForbidden();

        $this->assertNull(StudentEnrollment::query()->whereKey($aluno['id'])->value('enrolledCourseId'));
    }

    public function test_instrutor_nao_toma_o_aluno_do_curso_de_outro_instrutor(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Dono');
        $intruso = $this->instrutorComCurso('Instrutor Seguranca Intruso');
        $aluno = $this->makeStudent('Aluno Seguranca Alheio');

        // O dono matricula o aluno no curso dele.
        $this->withHeader('Authorization', 'Bearer '.$dono['token'])
            ->putJson('/api/enrollments/'.$aluno['id'], ['enrolledCourseId' => $dono['curso']->id])
            ->assertOk();

        // O intruso tenta passá-lo para o curso dele.
        $this->withHeader('Authorization', 'Bearer '.$intruso['token'])
            ->putJson('/api/enrollments/'.$aluno['id'], ['enrolledCourseId' => $intruso['curso']->id])
            ->assertForbidden();

        $this->assertSame(
            $dono['curso']->id,
            StudentEnrollment::query()->whereKey($aluno['id'])->value('enrolledCourseId'),
        );
    }

    public function test_instrutor_ainda_matricula_aluno_sem_matricula_no_proprio_curso(): void
    {
        $eu = $this->instrutorComCurso('Instrutor Seguranca Normal');
        $aluno = $this->makeStudent('Aluno Seguranca Normal');

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->putJson('/api/enrollments/'.$aluno['id'], ['enrolledCourseId' => $eu['curso']->id])
            ->assertOk()
            ->assertJsonPath('enrolledCourseId', $eu['curso']->id);
    }

    public function test_as_telas_saem_com_nosniff_e_sem_poder_ser_embutidas_por_outro_site(): void
    {
        foreach (['/', '/o-ava', '/entrar', '/uma-tela-que-nunca-existiu'] as $caminho) {
            $resposta = $this->get($caminho);
            $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
            $resposta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }
    }

    public function test_instrutor_nao_sequestra_aula_nem_encontro_de_outro_curso_pelo_put_do_curso(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Aulas Dono');
        $intruso = $this->instrutorComCurso('Instrutor Seguranca Aulas Intruso');
        $aula = Lesson::query()->create([
            'id' => 'lesson-seg-'.uniqid(), 'courseId' => $dono['curso']->id, 'title' => 'Aula Do Dono',
            'duration' => '10 min', 'content' => 'Texto do dono.', 'lesson_order' => 1,
        ]);
        $encontro = LiveSession::query()->create([
            'id' => 'live-seg-'.uniqid(), 'courseId' => $dono['curso']->id, 'title' => 'Encontro Do Dono',
            'scheduledAt' => '2030-01-01T10:00', 'durationMinutes' => 60, 'meetingLink' => 'https://example.com/sala-do-dono',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$intruso['token'])
            ->putJson('/api/courses/'.$intruso['curso']->id, [
                'lessons' => [['id' => $aula->id, 'title' => 'Aula Sequestrada', 'duration' => '1 min', 'content' => 'hack', 'order' => 0]],
                'liveSessions' => [['id' => $encontro->id, 'title' => 'Encontro Sequestrado', 'scheduledAt' => '2030-02-02T10:00', 'durationMinutes' => 60, 'meetingLink' => 'https://example.com/sala-do-intruso']],
            ])->assertOk();

        $aulaDepois = Lesson::query()->findOrFail($aula->id);
        $this->assertSame($dono['curso']->id, $aulaDepois->courseId);
        $this->assertSame('Aula Do Dono', $aulaDepois->title);
        $this->assertSame('Texto do dono.', $aulaDepois->content);

        $encontroDepois = LiveSession::query()->findOrFail($encontro->id);
        $this->assertSame($dono['curso']->id, $encontroDepois->courseId);
        $this->assertSame('Encontro Do Dono', $encontroDepois->title);

        // O conteúdo do intruso entrou como aula NOVA, no curso dele.
        $this->assertSame(1, Lesson::query()->where('courseId', $intruso['curso']->id)->where('title', 'Aula Sequestrada')->count());
    }

    public function test_aluno_nao_registra_progresso_nem_pede_certificado_de_curso_em_que_nao_esta(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Progresso');
        $aula = Lesson::query()->create([
            'id' => 'lesson-prog-'.uniqid(), 'courseId' => $dono['curso']->id, 'title' => 'Unica Aula',
            'duration' => '10 min', 'content' => 'x', 'lesson_order' => 1,
        ]);
        $aluno = $this->makeStudent('Aluno Seguranca Forja');
        $h = ['Authorization' => 'Bearer '.$aluno['token']];

        // Os ids de aula saem no catálogo público: bastava mandá-los para "cursar".
        $this->postJson('/api/progress', ['courseId' => $dono['curso']->id, 'completedLessons' => [$aula->id]], $h)->assertForbidden();
        $this->postJson('/api/certificates', ['courseId' => $dono['curso']->id], $h)->assertForbidden();
    }

    public function test_aluno_matriculado_segue_registrando_progresso(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Progresso Ok');
        $aula = Lesson::query()->create([
            'id' => 'lesson-prog-ok-'.uniqid(), 'courseId' => $dono['curso']->id, 'title' => 'Unica Aula',
            'duration' => '10 min', 'content' => 'x', 'lesson_order' => 1,
        ]);
        $aluno = $this->makeStudent('Aluno Seguranca Progresso Ok');
        $this->matricular($aluno, $dono['curso']->id);

        $this->postJson('/api/progress', ['courseId' => $dono['curso']->id, 'completedLessons' => [$aula->id]], ['Authorization' => 'Bearer '.$aluno['token']])
            ->assertOk();
    }

    public function test_consulta_publica_de_certificado_nao_enumera_alunos_por_pedaco_de_nome(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Cert');
        $aluno = $this->makeStudent('Zenaide Quaresma Seguranca');
        $cert = Certificate::query()->create([
            'id' => 'cert-seg-'.uniqid(), 'studentName' => $aluno['name'], 'userId' => $aluno['id'],
            'courseId' => $dono['curso']->id, 'courseTitle' => $dono['curso']->title, 'issueDate' => now(),
            'attendancePercent' => 100, 'verificationHash' => 'AVA-SEG-'.strtoupper(uniqid()),
        ]);

        foreach (['Zen', '%', '_', 'Quaresma', 'a', ' '] as $pedaco) {
            $resposta = $this->getJson('/api/certificates/verify?q='.rawurlencode($pedaco));
            $this->assertNotSame($cert->verificationHash, $resposta->json('verificationHash'), "«{$pedaco}» achou o certificado");
        }

        // O código e o nome completo continuam achando.
        $this->getJson('/api/certificates/verify?q='.$cert->verificationHash)->assertOk()->assertJsonPath('verificationHash', $cert->verificationHash);
        $this->getJson('/api/certificates/verify?q='.rawurlencode($aluno['name']))->assertOk()->assertJsonPath('verificationHash', $cert->verificationHash);
    }

    public function test_admin_bloqueado_ou_rebaixado_nao_provisiona_administrador_com_o_token_antigo(): void
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id, 'name' => 'Admin Que Caiu', 'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT), 'role' => 'admin',
        ]);
        $token = Jwt::issue($id, 'Admin Que Caiu', 'admin');
        $corpo = fn (): array => [
            'name' => 'Admin Clandestino', 'email' => 'id-'.uniqid().'@example.com', 'password' => 'senha123456', 'role' => 'admin',
        ];

        // Enquanto é admin de verdade, a chamada vale (prova de que o teste não passa por outro motivo).
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/auth/register', $corpo())->assertCreated();

        // Bloqueado: o token de 12 h continua assinado, mas não vale mais. A conta vale como visitante,
        // e visitante não cria administrador (a recusa pode vir como 400 ou 403, conforme a regra que pegar primeiro).
        User::query()->whereKey($id)->update(['status' => 'blocked']);
        $bloqueado = $corpo();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/auth/register', $bloqueado);
        $this->assertFalse(User::query()->where('email', $bloqueado['email'])->exists());

        // Rebaixado a aluno e reativado: o papel vem do banco, não do token.
        User::query()->whereKey($id)->update(['status' => 'active', 'role' => 'student']);
        $rebaixado = $corpo();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/auth/register', $rebaixado);
        $this->assertFalse(User::query()->where('email', $rebaixado['email'])->exists());
    }

    public function test_aluno_nao_se_matricula_em_curso_em_rascunho_restrito_ou_inexistente_pela_api(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Matricula');
        $aluno = $this->makeStudent('Aluno Seguranca Matricula');
        $h = ['Authorization' => 'Bearer '.$aluno['token']];

        foreach ([Visibilidade::RASCUNHO, Visibilidade::RESTRITO] as $nivel) {
            Course::query()->whereKey($dono['curso']->id)->update(['status' => $nivel]);

            $this->postJson('/api/enrollments/self/enroll', ['courseId' => $dono['curso']->id], $h)->assertNotFound();
            $this->postJson('/api/admissions', ['courseId' => $dono['curso']->id], $h)->assertNotFound();
        }
        $this->postJson('/api/enrollments/self/enroll', ['courseId' => 'curso-que-nunca-existiu'], $h)->assertNotFound();
        $this->postJson('/api/admissions', ['courseId' => 'curso-que-nunca-existiu'], $h)->assertNotFound();

        $this->assertNull(StudentEnrollment::query()->whereKey($aluno['id'])->value('enrolledCourseId'));

        // Publicado, a matrícula segue funcionando.
        Course::query()->whereKey($dono['curso']->id)->update(['status' => Visibilidade::PUBLICADO]);
        $this->postJson('/api/enrollments/self/enroll', ['courseId' => $dono['curso']->id], $h)->assertOk();
    }

    public function test_curtir_mensagem_de_forum_e_entregar_exercicio_exigem_pertencer_ao_curso(): void
    {
        config(['features.forum' => true, 'features.atividadesPraticasAvancadas' => true]);
        $dono = $this->instrutorComCurso('Instrutor Seguranca Forum');
        $mensagem = ForumMessage::query()->create([
            'id' => 'forum-seg-'.uniqid(), 'courseId' => $dono['curso']->id, 'senderName' => 'Quem Escreveu',
            'senderUserId' => $dono['id'], 'senderRole' => 'instructor', 'text' => 'Mensagem privada do curso.',
            'timestamp' => '23/10/2026 10:00', 'likes' => 0, 'likedBy' => [],
        ]);
        $exercicio = PracticalExercise::query()->create([
            'id' => 'ex-seg-'.uniqid(), 'courseId' => $dono['curso']->id, 'title' => 'Exercicio Do Curso',
            'description' => 'x', 'instructions' => 'x', 'maxPoints' => 10, 'dueDate' => '31/12/2030',
        ]);
        $estranho = $this->makeStudent('Aluno Seguranca Forum Estranho');
        $deDentro = $this->makeStudent('Aluno Seguranca Forum Dentro');
        $this->matricular($deDentro, $dono['curso']->id);

        $fora = ['Authorization' => 'Bearer '.$estranho['token']];
        $dentro = ['Authorization' => 'Bearer '.$deDentro['token']];

        $this->putJson('/api/forum/'.$mensagem->id.'/like', [], $fora)->assertForbidden();
        $this->postJson('/api/exercise-submissions', ['exerciseId' => $exercicio->id, 'submissionText' => 'minha entrega'], $fora)->assertForbidden();
        $this->postJson('/api/exercise-submissions', ['exerciseId' => 'nao-existe', 'submissionText' => 'x'], $dentro)->assertNotFound();
        $this->postJson('/api/exercise-submissions', ['exerciseId' => $exercicio->id, 'submissionText' => 'x', 'fileUrl' => 'javascript:alert(1)'], $dentro)->assertStatus(400);

        $this->putJson('/api/forum/'.$mensagem->id.'/like', [], $dentro)->assertOk();
        $this->postJson('/api/exercise-submissions', ['exerciseId' => $exercicio->id, 'submissionText' => 'minha entrega'], $dentro)->assertCreated();
    }

    public function test_o_papel_da_tela_de_entrar_nao_vira_javascript(): void
    {
        $ataque = "');alert(document.cookie)//";

        $resposta = $this->get('/entrar?papel='.rawurlencode($ataque));

        $resposta->assertOk();
        $resposta->assertDontSee('alert(document.cookie)', false);
        // Um valor desconhecido cai no padrão (aluno); os conhecidos continuam valendo.
        $this->get('/entrar?papel=admin')->assertOk();
        $this->get('/entrar?papel[]=x')->assertOk();
    }

    public function test_id_de_curso_com_curinga_de_like_e_recusado(): void
    {
        $eu = $this->instrutorComCurso('Instrutor Seguranca Id');

        foreach (['%', 'a_b', 'a%b', 'com espaco', "x'y"] as $id) {
            $this->withHeader('Authorization', 'Bearer '.$eu['token'])->postJson('/api/courses', [
                'id' => $id, 'title' => 'Curso Com Id Estranho', 'description' => 'Descrição longa o bastante para o validador aceitar.',
                'category' => 'Teste', 'thumbnail' => 'sem-imagem.png', 'instructorName' => 'x',
            ])->assertStatus(400);
        }
    }

    public function test_falha_de_login_nao_grava_o_cpf_inteiro_nem_texto_fora_do_formato(): void
    {
        $ultimo = static fn (): string => (string) DB::table('SecurityLog')->where('action', 'Tentativa Fracassada')
            ->orderByDesc('ocorridoEm')->orderByDesc('id')->value('details');

        $this->postJson('/api/auth/login', ['cpf' => '529.982.247-25', 'password' => 'errada', 'role' => 'student']);
        $this->assertStringContainsString('***.***.***-25', $ultimo());
        $this->assertStringNotContainsString('529.982.247', $ultimo());

        // Pela tela de entrar o aluno também digita CPF; um texto qualquer (uma senha no campo errado) não é gravado.
        $this->post('/entrar', ['papel' => 'student', 'identificador' => 'MinhaSenhaSecreta#2026', 'senha' => 'x']);
        $this->assertStringNotContainsString('MinhaSenhaSecreta', $ultimo());
    }

    public function test_aluno_nao_responde_prova_em_rascunho_ou_restrita(): void
    {
        config(['features.quizSimples' => true]);
        $dono = $this->instrutorComCurso('Instrutor Seguranca Prova');
        $aluno = $this->makeStudent('Aluno Seguranca Prova');
        $this->matricular($aluno, $dono['curso']->id);

        $quizId = 'quiz-seg-'.uniqid();
        DB::table('Quiz')->insert(['id' => $quizId, 'courseId' => $dono['curso']->id, 'title' => 'Prova Em Rascunho', 'status' => Visibilidade::RASCUNHO]);
        DB::table('QuizQuestion')->insert([
            'id' => $quizId.'-q1', 'quizId' => $quizId, 'questionText' => 'Pergunta?', 'options' => json_encode(['a', 'b']),
            'correctOptionIndex' => 0,
        ]);

        $this->postJson('/api/quiz-submissions', ['quizId' => $quizId, 'answers' => [$quizId.'-q1' => 0]], ['Authorization' => 'Bearer '.$aluno['token']])
            ->assertNotFound();

        DB::table('Quiz')->where('id', $quizId)->update(['status' => Visibilidade::PUBLICADO]);
        $this->postJson('/api/quiz-submissions', ['quizId' => $quizId, 'answers' => [$quizId.'-q1' => 0]], ['Authorization' => 'Bearer '.$aluno['token']])
            ->assertSuccessful();
    }

    public function test_admin_continua_podendo_registrar_conclusao(): void
    {
        $aluno = $this->makeStudent('Aluno Seguranca Admin');
        $curso = $this->anySeededCourseId();

        $this->withHeader('Authorization', 'Bearer '.$this->staffToken('admin'))
            ->putJson('/api/enrollments/'.$aluno['id'], ['completedCourseIds' => [$curso]])
            ->assertOk();
    }
}
