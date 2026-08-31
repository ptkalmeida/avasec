<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Regressão de segurança — cada teste trava um exploit concreto encontrado no pentest
 * (e confirmado ao vivo) para que não reapareça. Requer o MySQL de dev populado.
 * Escritas em transação revertida.
 */
final class SecurityRegressionTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    // #4 — o gabarito (correctOptionIndex) não pode ser raspado sem autenticação.
    public function test_listing_quizzes_requires_authentication(): void
    {
        $this->getJson('/api/quizzes')->assertStatus(401);
    }

    // #1 — aluno não lista usuários (vazava e-mails de todos, inclusive admins).
    public function test_student_cannot_list_users(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        $this->getJson('/api/auth/users?role=admin', $auth)->assertStatus(403);
        $this->getJson('/api/auth/users?role=instructor', $auth)->assertStatus(403);
        $this->getJson('/api/auth/users', $auth)->assertStatus(403);
    }

    // #1 — instrutor, mesmo pedindo role=admin, nunca recebe admins/instrutores.
    public function test_instructor_listing_users_never_returns_admins(): void
    {
        $token = $this->staffToken('instructor');
        $items = $this->getJson('/api/auth/users?role=admin', $this->auth($token))
            ->assertOk()->json('items');
        $this->assertIsArray($items);
        foreach ($items as $u) {
            $role = is_array($u) ? ($u['role'] ?? null) : null;
            $this->assertSame('student', $role, 'Instrutor recebeu usuário não-aluno.');
        }
    }

    // #3 — a nota do quiz é calculada no servidor; o cliente não a auto-declara.
    public function test_quiz_score_is_computed_server_side(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        // Gabarito de quiz-1 (seed): q1=0, q2=1, q3=2.
        $correct = ['quiz-1-q1' => 0, 'quiz-1-q2' => 1, 'quiz-1-q3' => 2];
        $wrong = ['quiz-1-q1' => 1, 'quiz-1-q2' => 0, 'quiz-1-q3' => 0];

        // Mesmo mandando scorePercent/passed no corpo, o servidor ignora e recalcula.
        $res = $this->postJson('/api/quiz-submissions', [
            'quizId' => 'quiz-1',
            'answers' => $wrong,
            'scorePercent' => 100,
            'passed' => true,
        ], $auth)->assertStatus(201);
        $res->assertJsonPath('scorePercent', 0)->assertJsonPath('passed', false);

        $res2 = $this->postJson('/api/quiz-submissions', [
            'quizId' => 'quiz-1',
            'answers' => $correct,
        ], $auth)->assertStatus(201);
        $res2->assertJsonPath('scorePercent', 100)->assertJsonPath('passed', true);
    }

    // #2 — progresso só aceita IDs de aulas reais do curso; IDs inventados somem.
    public function test_progress_ignores_fake_lesson_ids(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        $this->postJson('/api/progress', [
            'courseId' => 'course-1',
            'completedLessons' => ['fake-1', 'fake-2', 'fake-3', 'fake-4', 'fake-5', 'lesson-1-1', 'lesson-1-1'],
        ], $auth)->assertOk();

        $progress = $this->getJson('/api/progress', $auth)->assertOk()->json();
        $this->assertIsArray($progress);
        $completed = null;
        foreach ($progress as $row) {
            if (is_array($row) && ($row['courseId'] ?? null) === 'course-1') {
                $completed = $row['completedLessons'] ?? null;
                break;
            }
        }
        // Só o ID real e único sobrevive; os inventados e a duplicata são descartados.
        $this->assertSame(['lesson-1-1'], $completed);
    }

    // #2 — sem frequência real, a emissão de certificado é recusada (não forjável).
    public function test_certificate_forgery_is_blocked(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        $this->postJson('/api/progress', [
            'courseId' => 'course-1',
            'completedLessons' => ['x1', 'x2', 'x3', 'x4', 'x5', 'x6', 'x7', 'x8', 'x9', 'x10'],
        ], $auth)->assertOk();

        $this->postJson('/api/certificates', ['courseId' => 'course-1'], $auth)->assertStatus(403);
    }

    // #5 — trocar a senha exige a senha atual (bloqueia takeover de sessão sequestrada).
    public function test_change_password_requires_current_password(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        // Sem currentPassword -> validação falha (400).
        $this->putJson('/api/auth/password', ['newPassword' => 'NovaSenha999'], $auth)->assertStatus(400);
        // Com senha atual errada -> 401.
        $this->putJson('/api/auth/password', [
            'currentPassword' => 'errada-total',
            'newPassword' => 'NovaSenha999',
        ], $auth)->assertStatus(401);
        // Com a senha atual correta (definida em makeStudent) -> 200.
        $this->putJson('/api/auth/password', [
            'currentPassword' => 'senha123456',
            'newPassword' => 'NovaSenha999',
        ], $auth)->assertOk();
    }

    // #8 — um token com role forjado não vale: o role é reconferido no banco.
    public function test_forged_admin_role_in_token_is_overridden_by_database(): void
    {
        $student = $this->makeStudent();
        // Token dizendo admin para um usuário que é student no banco.
        $forged = Jwt::issue($student['id'], $student['name'], 'admin');

        // Rota admin-only: o RequireActiveAccount sobrescreve o role com 'student' -> 403.
        $this->putJson('/api/system-settings', ['foo' => 'bar'], $this->auth($forged))
            ->assertStatus(403);
    }

    // #7 — instrutor só vê certificados dos cursos que leciona (não a plataforma toda).
    public function test_instructor_certificate_list_is_scoped_to_own_courses(): void
    {
        $token = $this->staffToken('instructor');
        $ownCourseIds = DB::table('Course')
            ->where('instructorId', DB::table('User')->where('role', 'instructor')->value('id'))
            ->pluck('id')->all();

        $items = $this->getJson('/api/certificates', $this->auth($token))->assertOk()->json('items');
        $this->assertIsArray($items);
        foreach ($items as $cert) {
            $courseId = is_array($cert) ? ($cert['courseId'] ?? null) : null;
            $this->assertContains($courseId, $ownCourseIds, 'Instrutor viu certificado de curso alheio.');
        }
    }

    // URL de curso só era validada como "string, max:2000": um instrutor podia gravar
    // javascript: em thumbnail, coverImage, documents[].url ou meetingLink, e o valor
    // ia direto para um href na tela do aluno. O cookie é HttpOnly, mas viaja sozinho,
    // então o script agiria como a vítima.
    public function test_course_rejects_javascript_url_in_every_url_field(): void
    {
        $token = $this->staffToken('instructor');
        $base = [
            'title' => 'Curso XSS Teste',
            'description' => 'Descrição suficientemente longa para passar na validação.',
            'category' => 'Teste',
            'thumbnail' => 'https://exemplo.com/capa.png',
        ];

        $variantes = [
            'thumbnail' => ['thumbnail' => 'javascript:alert(1)'],
            'coverImage' => ['coverImage' => 'javascript:alert(1)'],
            'documents.url' => ['lessons' => [[
                'title' => 'Aula 1', 'duration' => '10min', 'order' => 0,
                'documents' => [['title' => 'Doc', 'type' => 'url', 'url' => 'javascript:alert(1)']],
            ]]],
            'meetingLink' => ['liveSessions' => [[
                'title' => 'Encontro', 'scheduledAt' => '2026-09-01T10:00:00Z',
                'durationMinutes' => 60, 'meetingLink' => 'javascript:alert(1)',
            ]]],
        ];

        foreach ($variantes as $campo => $payload) {
            $this->postJson('/api/courses', array_merge($base, $payload), $this->auth($token))
                ->assertStatus(400, "Campo {$campo} aceitou javascript:");
        }
    }

    public function test_library_item_rejects_javascript_url(): void
    {
        $this->postJson('/api/library', [
            'title' => 'Item XSS',
            'type' => 'link',
            'category' => 'Teste',
            'url' => 'javascript:alert(1)',
        ], $this->auth($this->staffToken('instructor')))->assertStatus(400);
    }
}
