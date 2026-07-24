<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Etapa 3 — Certificados. Espelha certificateService/certificateRoutes e os testes
 * de regra de negócio do Node (emissão só com frequência; não emitir por terceiro).
 * Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class CertificateTest extends TestCase
{
    use DatabaseTransactions;

    private function studentToken(): string
    {
        $u = DB::table('User')->where('role', 'student')->where('status', 'active')->first(['id', 'name']);
        $this->assertNotNull($u);

        return Jwt::issue($u->id, $u->name, 'student');
    }

    public function test_verify_is_public(): void
    {
        // Sem token, deve responder 200 (cert ou null) — nunca 401.
        $this->getJson('/api/certificates/verify?q=AVA-INEXISTENTE')
            ->assertOk();
    }

    public function test_issue_without_meeting_attendance_is_forbidden(): void
    {
        $u = DB::table('User')->where('role', 'student')->where('status', 'active')->first(['id', 'name']);
        $token = Jwt::issue($u->id, $u->name, 'student');

        // Curso sem progresso registrado para este aluno -> frequência insuficiente.
        $courseId = DB::table('Course')->value('id');
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/certificates', ['studentName' => $u->name, 'courseId' => $courseId])
            ->assertStatus(403)
            ->assertJsonFragment(['error' => true]);
    }

    public function test_student_cannot_issue_certificate_for_another(): void
    {
        $token = $this->studentToken();
        $courseId = DB::table('Course')->value('id');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/certificates', ['studentName' => 'Outro Aluno Qualquer', 'courseId' => $courseId])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_certificate(): void
    {
        $token = $this->studentToken();
        $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson('/api/certificates/qualquer-id')
            ->assertStatus(403);
    }

    public function test_issue_is_idempotent_when_certificate_exists(): void
    {
        // Cria um aluno, matrícula e progresso suficientes num curso pequeno, emite 2x.
        $adminToken = DB::table('User')->where('role', 'admin')->first();
        $admin = Jwt::issue($adminToken->id, $adminToken->name, 'admin');

        $name = 'Aluno Cert '.uniqid();
        $reg = $this->withHeader('Authorization', "Bearer $admin")->postJson('/api/auth/register', [
            'name' => $name, 'email' => 'cert-'.uniqid().'@example.com', 'password' => 'senha123456', 'role' => 'student',
        ]);
        $studentId = $reg->json('user.id');
        $studentToken = Jwt::issue($studentId, $name, 'student');

        // Cria um curso com uma única aula (frequência 100% com 1 aula concluída).
        $courseId = 'course-cert-'.uniqid();
        $lessonId = 'lesson-cert-'.uniqid();
        DB::table('Course')->insert([
            'id' => $courseId, 'title' => 'Curso Cert', 'description' => 'desc', 'category' => 'x',
            'thumbnail' => 't', 'instructorName' => 'Gestor de Conteúdos',
        ]);
        DB::table('Lesson')->insert([
            'id' => $lessonId, 'courseId' => $courseId, 'title' => 'A1', 'duration' => '5min', 'lesson_order' => 0,
        ]);
        DB::table('StudentProgress')->insert([
            'id' => 'prog-cert-'.uniqid(), 'studentName' => $name, 'userId' => $studentId, 'courseId' => $courseId,
            'completedLessons' => json_encode([$lessonId]), 'attendedLiveSessions' => json_encode([]),
        ]);

        $first = $this->withHeader('Authorization', "Bearer $studentToken")
            ->postJson('/api/certificates', ['studentName' => $name, 'courseId' => $courseId]);
        $first->assertStatus(201)->assertJsonPath('attendancePercent', 100);
        $firstId = $first->json('id');

        // Segunda emissão retorna o MESMO certificado (idempotente).
        $second = $this->withHeader('Authorization', "Bearer $studentToken")
            ->postJson('/api/certificates', ['studentName' => $name, 'courseId' => $courseId]);
        $second->assertStatus(201)->assertJsonPath('id', $firstId);
    }
}
