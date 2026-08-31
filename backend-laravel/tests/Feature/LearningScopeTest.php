<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Escopo por curso em fórum, quizzes e exercícios práticos.
 *
 * O chat de aula (MessagingService) já filtrava por CourseAccess; estas três
 * listagens não recebiam o solicitante e devolviam o conteúdo de TODOS os cursos
 * para qualquer autenticado — incluindo as questões com gabarito de avaliações de
 * cursos que a pessoa nunca fez. A regra existia no projeto, faltava aqui.
 */
final class LearningScopeTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        // Fórum e exercícios estão com a flag DESLIGADA nesta versão (config/features.php),
        // o que hoje limita a exploração a 404 — mas flag é chave de liga/desliga, não
        // controle de acesso: ligar a funcionalidade não pode reabrir o vazamento. Por
        // isso o teste liga as duas e cobra o escopo com elas ativas.
        config(['features.forum' => true, 'features.atividadesPraticasAvancadas' => true]);
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    /** @param array<int, mixed> $itens @return list<string> */
    private function cursos(array $itens): array
    {
        $ids = [];
        foreach ($itens as $item) {
            if (is_array($item) && is_string($item['courseId'] ?? null)) {
                $ids[] = $item['courseId'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Confere que a seed tem mais de um curso na tabela, senão o teste é vacuo. */
    private function assertSeedTemVariosCursos(string $tabela): void
    {
        $this->assertGreaterThan(
            1,
            DB::table($tabela)->distinct()->count('courseId'),
            "Seed com um só curso em {$tabela}: o teste passaria sem provar escopo."
        );
    }

    public function test_student_without_enrollment_sees_no_forum_no_quizzes_no_exercises(): void
    {
        foreach (['ForumMessage', 'Quiz', 'PracticalExercise'] as $tabela) {
            $this->assertSeedTemVariosCursos($tabela);
        }

        $auth = $this->auth($this->makeStudent()['token']);

        $this->assertSame([], $this->getJson('/api/forum', $auth)->assertOk()->json());
        $this->assertSame([], $this->getJson('/api/quizzes', $auth)->assertOk()->json());
        $this->assertSame([], $this->getJson('/api/exercises', $auth)->assertOk()->json());
    }

    public function test_enrolled_student_sees_only_own_course(): void
    {
        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);

        $this->withHeaders($auth)
            ->postJson('/api/enrollments/self/enroll', ['courseId' => 'course-1'])
            ->assertOk();

        foreach (['/api/forum', '/api/quizzes', '/api/exercises'] as $rota) {
            $itens = $this->getJson($rota, $auth)->assertOk()->json();
            $this->assertNotEmpty($itens, "Aluno matriculado ficou sem conteúdo em {$rota}.");
            $this->assertSame(['course-1'], $this->cursos($itens), "Vazou curso alheio em {$rota}.");
        }
    }

    public function test_quiz_answer_key_of_other_course_is_not_reachable(): void
    {
        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);
        $this->withHeaders($auth)
            ->postJson('/api/enrollments/self/enroll', ['courseId' => 'course-1'])
            ->assertOk();

        $quizzes = $this->getJson('/api/quizzes', $auth)->assertOk()->json();

        // O gabarito continua vindo de propósito no curso do aluno (feedback imediato,
        // com a nota recalculada no servidor). O que não pode é vir de curso alheio.
        foreach ($quizzes as $quiz) {
            $this->assertSame('course-1', $quiz['courseId'] ?? null);
        }
        $this->assertNotEmpty($quizzes[0]['questions'] ?? []);
    }

    public function test_student_cannot_post_in_forum_of_course_they_do_not_attend(): void
    {
        $auth = $this->auth($this->makeStudent()['token']);

        // courseId vem do corpo: sem checagem, qualquer autenticado publicava em
        // qualquer turma. O chat de aula já barrava o caso equivalente.
        $this->postJson('/api/forum', ['courseId' => 'course-1', 'text' => 'Invasão de turma'], $auth)
            ->assertStatus(403);
    }

    public function test_student_can_post_in_forum_of_own_course(): void
    {
        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);
        $this->withHeaders($auth)
            ->postJson('/api/enrollments/self/enroll', ['courseId' => 'course-1'])
            ->assertOk();

        $this->postJson('/api/forum', ['courseId' => 'course-1', 'text' => 'Dúvida legítima'], $auth)
            ->assertStatus(201)
            ->assertJsonPath('courseId', 'course-1');
    }

    public function test_student_cannot_submit_quiz_of_course_they_do_not_attend(): void
    {
        $auth = $this->auth($this->makeStudent()['token']);

        $this->postJson('/api/quiz-submissions', [
            'quizId' => 'quiz-1',
            'answers' => ['quiz-1-q1' => 0],
        ], $auth)->assertStatus(403);
    }

    public function test_admin_keeps_seeing_everything(): void
    {
        $auth = $this->auth($this->staffToken('admin'));

        foreach (['/api/forum', '/api/quizzes', '/api/exercises'] as $rota) {
            $itens = $this->getJson($rota, $auth)->assertOk()->json();
            $this->assertGreaterThan(1, count($this->cursos($itens)), "Admin perdeu alcance em {$rota}.");
        }
    }

    public function test_instructor_sees_courses_they_teach(): void
    {
        $auth = $this->auth($this->staffToken('instructor'));

        // Instrutor não é admin: o alcance é o dos próprios cursos, e nunca mais que isso.
        $instructorId = DB::table('User')->where('role', 'instructor')->where('status', 'active')->value('id');
        $proprios = DB::table('Course')->where('instructorId', $instructorId)->pluck('id')->all();

        foreach (['/api/forum', '/api/quizzes', '/api/exercises'] as $rota) {
            foreach ($this->cursos($this->getJson($rota, $auth)->assertOk()->json()) as $courseId) {
                $this->assertContains($courseId, $proprios, "Instrutor viu curso alheio em {$rota}.");
            }
        }
    }
}
