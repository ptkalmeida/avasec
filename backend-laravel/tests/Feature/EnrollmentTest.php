<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Progresso, Matrículas e Admissões — identidade por userId (ADR 10).
 * Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class EnrollmentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_student_cannot_read_another_students_progress(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->getJson('/api/progress?userId='.urlencode($other['id']))
            ->assertStatus(403);
    }

    public function test_self_enroll_then_duplicate_active_is_409(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $courseId = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])
            ->assertOk()
            ->assertJsonPath('enrollment.enrolledCourseId', $courseId)
            ->assertJsonPath('enrollment.userId', $student['id']);

        // Segunda matrícula com uma ativa em curso: 409.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])
            ->assertStatus(409);
    }

    public function test_duplicate_pending_admission_is_409(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $courseId = $this->anySeededCourseId();
        // Identidade vem do token — o corpo não carrega mais studentName.
        $body = ['courseId' => $courseId];

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/admissions', $body)->assertStatus(201)
            ->assertJsonPath('userId', $student['id']);

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/admissions', $body)->assertStatus(409);
    }

    public function test_self_complete_without_attendance_is_forbidden(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $courseId = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])->assertOk();

        // Sem progresso registrado, a conclusão é barrada pelo critério de frequência.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/enrollments/self/complete', ['courseId' => $courseId])
            ->assertStatus(403);
    }

    public function test_student_cannot_create_admission_for_another_student(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/admissions', ['userId' => $other['id'], 'courseId' => $this->anySeededCourseId()])
            ->assertStatus(403);
    }

    public function test_get_enrollments_for_student_returns_own_keyed_map(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');

        $response = $this->withHeader('Authorization', "Bearer {$student['token']}")->getJson('/api/enrollments');
        $response->assertOk();
        // Contrato ADR 10: mapa { userId: { userId, studentName, enrolledCourseId, ... } }.
        $this->assertArrayHasKey($student['id'], $response->json());
        $this->assertSame($student['name'], $response->json($student['id'].'.studentName'));
    }

    public function test_admin_upserts_enrollment_by_user_id(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $courseId = $this->anySeededCourseId();
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/enrollments/'.$student['id'], ['enrolledCourseId' => $courseId])
            ->assertOk()
            ->assertJsonPath('enrolledCourseId', $courseId)
            ->assertJsonPath('userId', $student['id'])
            ->assertJsonPath('studentName', $student['name']);
    }

    public function test_homonyms_do_not_leak_each_others_enrollment(): void
    {
        // Dois alunos com o MESMO nome de exibição: cada um só vê a própria matrícula.
        $sharedName = 'Homônimo Teste '.uniqid();
        $a = $this->makeStudent(exactName: $sharedName);
        $b = $this->makeStudent(exactName: $sharedName);
        $courseId = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$a['token']}")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])->assertOk();

        $mapB = $this->withHeader('Authorization', "Bearer {$b['token']}")->getJson('/api/enrollments')->json();
        $this->assertArrayHasKey($b['id'], $mapB);
        $this->assertNull($mapB[$b['id']]['enrolledCourseId']);
        $this->assertArrayNotHasKey($a['id'], $mapB);
    }
}
