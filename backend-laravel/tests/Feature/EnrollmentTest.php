<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Etapa 3 — Progresso, Matrículas e Admissões. Espelha os casos de
 * enrollmentService/enrollmentRoutes e os testes de regra de negócio do Node.
 * Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class EnrollmentTest extends TestCase
{
    use DatabaseTransactions;

    private function tokenForRole(string $role): string
    {
        $u = DB::table('User')->where('role', $role)->where('status', 'active')->first(['id', 'name', 'role']);
        $this->assertNotNull($u, "Seed sem usuário ativo com role '$role'.");

        return Jwt::issue($u->id, $u->name, $u->role);
    }

    /** Cria um aluno descartável (sem matrícula) e devolve [token, name]. @return array{0:string,1:string} */
    private function freshStudent(): array
    {
        $adminToken = $this->tokenForRole('admin');
        $name = 'Aluno Enrollment '.uniqid();
        $res = $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/auth/register', [
            'name' => $name,
            'email' => 'enroll-'.uniqid().'@example.com',
            'password' => 'senha123456',
            'role' => 'student',
        ]);
        $res->assertStatus(201);
        $token = Jwt::issue($res->json('user.id'), $name, 'student');

        return [$token, $name];
    }

    private function anyCourseId(): string
    {
        $id = DB::table('Course')->value('id');
        $this->assertNotNull($id, 'Seed sem cursos — rode npm run db:seed.');

        return $id;
    }

    public function test_student_cannot_read_another_students_progress(): void
    {
        [$token] = $this->freshStudent();

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/progress?studentName='.urlencode('Gabriel Rodrigues'))
            ->assertStatus(403);
    }

    public function test_self_enroll_then_duplicate_active_is_409(): void
    {
        [$token] = $this->freshStudent();
        $courseId = $this->anyCourseId();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])
            ->assertOk()
            ->assertJsonPath('enrollment.enrolledCourseId', $courseId);

        // Segunda matrícula com uma ativa em curso: 409.
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])
            ->assertStatus(409);
    }

    public function test_duplicate_pending_admission_is_409(): void
    {
        [$token, $name] = $this->freshStudent();
        $courseId = $this->anyCourseId();
        $body = ['studentName' => $name, 'courseId' => $courseId];

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admissions', $body)->assertStatus(201);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admissions', $body)->assertStatus(409);
    }

    public function test_self_complete_without_attendance_is_forbidden(): void
    {
        [$token] = $this->freshStudent();
        $courseId = $this->anyCourseId();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])->assertOk();

        // Sem progresso registrado, a conclusão é barrada pelo critério de frequência.
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/enrollments/self/complete', ['courseId' => $courseId])
            ->assertStatus(403);
    }

    public function test_student_cannot_create_admission_for_another_student(): void
    {
        [$token] = $this->freshStudent();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admissions', ['studentName' => 'Outro Aluno Qualquer', 'courseId' => $this->anyCourseId()])
            ->assertStatus(403);
    }

    public function test_get_enrollments_for_student_returns_own_keyed_map(): void
    {
        [$token, $name] = $this->freshStudent();

        $response = $this->withHeader('Authorization', "Bearer $token")->getJson('/api/enrollments');
        $response->assertOk();
        // Contrato: mapa { nomeDoAluno: { enrolledCourseId, ... } }.
        $this->assertArrayHasKey($name, $response->json());
    }
}
