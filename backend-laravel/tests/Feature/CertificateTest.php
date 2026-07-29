<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Certificados — identidade por userId (ADR 10): emissão do próprio aluno deriva
 * do token; staff informa userId. Requer MySQL de dev populado.
 */
final class CertificateTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_verify_is_public(): void
    {
        // Sem token, deve responder 200 (cert ou null) — nunca 401.
        $this->getJson('/api/certificates/verify?q=AVA-INEXISTENTE')
            ->assertOk();
    }

    public function test_issue_without_meeting_attendance_is_forbidden(): void
    {
        $student = $this->makeStudent('Aluno Cert');

        // Curso sem progresso registrado para este aluno -> frequência insuficiente.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificates', ['courseId' => $this->anySeededCourseId()])
            ->assertStatus(403)
            ->assertJsonFragment(['error' => true]);
    }

    public function test_student_cannot_issue_certificate_for_another(): void
    {
        $student = $this->makeStudent('Aluno Cert');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificates', ['userId' => $other['id'], 'courseId' => $this->anySeededCourseId()])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_certificate(): void
    {
        $student = $this->makeStudent('Aluno Cert');
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->deleteJson('/api/certificates/qualquer-id')
            ->assertStatus(403);
    }

    public function test_issue_is_idempotent_when_certificate_exists(): void
    {
        $student = $this->makeStudent('Aluno Cert');

        // Curso com uma única aula (frequência 100% com 1 aula concluída).
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
            'id' => 'prog-cert-'.uniqid(), 'studentName' => $student['name'], 'userId' => $student['id'], 'courseId' => $courseId,
            'completedLessons' => json_encode([$lessonId]), 'attendedLiveSessions' => json_encode([]),
        ]);

        $first = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificates', ['courseId' => $courseId]);
        $first->assertStatus(201)
            ->assertJsonPath('attendancePercent', 100)
            ->assertJsonPath('userId', $student['id'])
            ->assertJsonPath('studentName', $student['name']);
        $firstId = $first->json('id');

        // Segunda emissão retorna o MESMO certificado (idempotente por userId+courseId).
        $second = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificates', ['courseId' => $courseId]);
        $second->assertStatus(201)->assertJsonPath('id', $firstId);
    }
}
