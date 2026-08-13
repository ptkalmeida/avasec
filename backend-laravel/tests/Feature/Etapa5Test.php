<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Etapa 5 — módulos restantes (learning, mensagens, solicitações, settings, auditoria,
 * telemetria, export). Cobre os comportamentos-chave e os casos-espelho dos testes do Node.
 * Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class Etapa5Test extends TestCase
{
    use DatabaseTransactions;

    private function token(string $role): string
    {
        $u = DB::table('User')->where('role', $role)->where('status', 'active')->first(['id', 'name', 'role']);
        $this->assertNotNull($u, "Seed sem usuário ativo com role '$role'.");

        return Jwt::issue($u->id, $u->name, $u->role);
    }

    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer $token"];
    }

    // ---------- Export ----------

    public function test_admin_exports_students_without_password_hash(): void
    {
        $res = $this->getJson('/api/export/students', $this->auth($this->token('admin')));
        $res->assertOk()->assertJsonPath('dataset', 'students');
        $data = $res->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('passwordHash', $data[0] ?? []);
    }

    public function test_student_cannot_export(): void
    {
        $this->getJson('/api/export/students', $this->auth($this->token('student')))->assertStatus(403);
    }

    public function test_instructor_cannot_export(): void
    {
        $this->getJson('/api/export/students', $this->auth($this->token('instructor')))->assertStatus(403);
    }

    public function test_export_courses_and_progress_shapes(): void
    {
        $admin = $this->auth($this->token('admin'));
        $courses = $this->getJson('/api/export/courses', $admin)->assertOk()->json('data');
        $this->assertArrayHasKey('minAttendance', $courses[0]);
        $this->assertArrayHasKey('areaTematica', $courses[0]);

        // progress inclui as FKs (hardening).
        $prog = $this->getJson('/api/export/progress', $admin)->assertOk()->json('data');
        if (! empty($prog)) {
            $this->assertArrayHasKey('userId', $prog[0]);
            $this->assertArrayHasKey('enrollmentId', $prog[0]);
        }
    }

    public function test_invalid_export_dataset_is_bad_request(): void
    {
        $this->getJson('/api/export/senhas', $this->auth($this->token('admin')))
            ->assertStatus(400)->assertJsonPath('code', 'BAD_REQUEST');
    }

    // ---------- Auditoria x Telemetria ----------

    public function test_security_logs_have_no_post_route(): void
    {
        // Auditoria é gravada só pelo servidor — não existe POST.
        $this->postJson('/api/security-logs', ['x' => 1], $this->auth($this->token('admin')))
            ->assertStatus(405);
    }

    public function test_security_logs_list_is_admin_only(): void
    {
        $this->getJson('/api/security-logs', $this->auth($this->token('admin')))->assertOk()
            ->assertJsonStructure(['items', 'pagination']);
        $this->getJson('/api/security-logs', $this->auth($this->token('student')))->assertStatus(403);
    }

    public function test_telemetry_accepts_anonymous_and_read_is_admin_only(): void
    {
        // POST anônimo (sem token) é aceito.
        $this->postJson('/api/telemetry', ['action' => 'nav', 'details' => 'abriu painel'], ['Accept' => 'application/json'])
            ->assertStatus(201);
        // Leitura só admin.
        $this->getJson('/api/telemetry', ['Accept' => 'application/json'])->assertStatus(401);
        $this->getJson('/api/telemetry', $this->auth($this->token('admin')))->assertOk();
    }

    // ---------- Settings ----------

    public function test_settings_get_is_public_and_put_is_admin_only(): void
    {
        $this->getJson('/api/system-settings')->assertOk();
        $this->putJson('/api/system-settings', ['tema' => 'x'], $this->auth($this->token('student')))->assertStatus(403);
        $this->putJson('/api/system-settings', ['testFlag' => true], $this->auth($this->token('admin')))
            ->assertOk()->assertJsonPath('testFlag', true);
    }

    // ---------- Learning ----------

    public function test_quizzes_list_requires_authentication(): void
    {
        // Segurança: o gabarito (correctOptionIndex) não pode ser raspado anonimamente.
        $this->getJson('/api/quizzes')->assertStatus(401);

        $res = $this->getJson('/api/quizzes', $this->auth($this->token('student')));
        $res->assertOk();
        $this->assertIsArray($res->json());
    }

    public function test_quiz_submission_is_student_only(): void
    {
        $this->postJson('/api/quiz-submissions', ['courseId' => 'c', 'quizId' => 'q', 'scorePercent' => 80, 'passed' => true], $this->auth($this->token('instructor')))
            ->assertStatus(403);
    }

    // ---------- Feature gate ----------

    public function test_forum_is_disabled_by_feature_flag(): void
    {
        // forum flag desligada por padrão -> 404 FEATURE_DISABLED (não só escondido na UI).
        $this->getJson('/api/forum', $this->auth($this->token('student')))
            ->assertStatus(404)->assertJsonPath('code', 'FEATURE_DISABLED');
    }
}
