<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Matricula;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Progresso, Matrículas e Admissões — identidade por usuario_id (ADR 10).
 * Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class EnrollmentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ativarCursosDeSeed();
    }

    public function test_student_cannot_read_another_students_progress(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->getJson('/api/progresso?usuario_id='.urlencode($other['id']))
            ->assertStatus(403);
    }

    public function test_self_enroll_then_duplicate_active_is_409(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $curso_id = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])
            ->assertOk()
            ->assertJsonPath('enrollment.curso_matriculado_id', $curso_id)
            ->assertJsonPath('enrollment.usuario_id', $student['id']);

        // Segunda matrícula com uma ativa em curso: 409.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])
            ->assertStatus(409);
    }

    public function test_duplicate_pending_admission_is_409(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $curso_id = $this->anySeededCourseId();
        // Identidade vem do token — o corpo não carrega mais nome_aluno.
        $body = ['curso_id' => $curso_id];

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/ingressos', $body)->assertStatus(201)
            ->assertJsonPath('usuario_id', $student['id']);

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/ingressos', $body)->assertStatus(409);
    }

    public function test_self_complete_without_attendance_is_forbidden(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $curso_id = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])->assertOk();

        // Sem progresso registrado, a conclusão é barrada pelo critério de frequência.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/concluir', ['curso_id' => $curso_id])
            ->assertStatus(403);
    }

    public function test_student_cannot_create_admission_for_another_student(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/ingressos', ['usuario_id' => $other['id'], 'curso_id' => $this->anySeededCourseId()])
            ->assertStatus(403);
    }

    public function test_get_enrollments_for_student_returns_own_keyed_map(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');

        $response = $this->withHeader('Authorization', "Bearer {$student['token']}")->getJson('/api/matriculas');
        $response->assertOk();
        // Contrato ADR 10: mapa { usuario_id: { usuario_id, nome_aluno, curso_matriculado_id, ... } }.
        $this->assertArrayHasKey($student['id'], $response->json());
        $this->assertSame($student['nome'], $response->json($student['id'].'.nome_aluno'));
    }

    public function test_admin_upserts_enrollment_by_user_id(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $curso_id = $this->anySeededCourseId();
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/matriculas/'.$student['id'], ['curso_matriculado_id' => $curso_id])
            ->assertOk()
            ->assertJsonPath('curso_matriculado_id', $curso_id)
            ->assertJsonPath('usuario_id', $student['id'])
            ->assertJsonPath('nome_aluno', $student['nome']);
    }

    public function test_second_enroll_still_blocked_when_flag_on_but_permission_not_granted(): void
    {
        config(['features.matriculasMultiplas' => true]);

        $student = $this->makeStudent('Aluno Enrollment');
        $courseA = $this->anySeededCourseId();
        $courseB = $this->anotherSeededCourseId($courseA);

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $courseA])->assertOk();

        // Flag global ligada não basta — falta a permissão individual do aluno.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $courseB])
            ->assertStatus(409);
    }

    public function test_admin_grants_multi_enroll_permission_and_student_gets_second_active_course(): void
    {
        config(['features.matriculasMultiplas' => true]);

        $student = $this->makeStudent('Aluno Enrollment');
        $admin = $this->staffToken('admin');
        $courseA = $this->anySeededCourseId();
        $courseB = $this->anotherSeededCourseId($courseA);

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/matriculas/'.$student['id'], ['pode_multimatricula' => true])
            ->assertOk()
            ->assertJsonPath('pode_multimatricula', true);

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $courseA])->assertOk();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $courseB])
            ->assertOk()
            ->assertJsonPath('enrollment.curso_matriculado_id', $courseA)
            ->assertJsonPath('enrollment.cursos_extras_ids', [$courseB]);

        // Cancelar a matrícula extra não afeta a matrícula principal.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/desistir', ['curso_id' => $courseB])
            ->assertOk()
            ->assertJsonPath('enrollment.curso_matriculado_id', $courseA)
            ->assertJsonPath('enrollment.cursos_extras_ids', []);
    }

    public function test_self_enroll_in_already_completed_course_is_409(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $curso_id = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])->assertOk();

        // Estado pós-conclusão: sem matrícula ativa e o curso na lista de concluídos.
        Matricula::query()->whereKey($student['id'])->firstOrFail()->update([
            'curso_matriculado_id' => null,
            'matriculado_em_texto' => null,
            'cursos_concluidos_ids' => [$curso_id],
        ]);

        // Curso concluído não aceita nova matrícula, mesmo sem matrícula ativa.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_instructor_cannot_grant_multi_enroll_permission(): void
    {
        $student = $this->makeStudent('Aluno Enrollment');
        $instructor = $this->staffToken('instructor');

        $this->withHeader('Authorization', "Bearer {$instructor}")
            ->putJson('/api/matriculas/'.$student['id'], ['pode_multimatricula' => true])
            ->assertStatus(403);
    }

    public function test_homonyms_do_not_leak_each_others_enrollment(): void
    {
        // Dois alunos com o MESMO nome de exibição: cada um só vê a própria matrícula.
        $sharedName = 'Homônimo Teste '.uniqid();
        $a = $this->makeStudent(exactName: $sharedName);
        $b = $this->makeStudent(exactName: $sharedName);
        $curso_id = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$a['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])->assertOk();

        $mapB = $this->withHeader('Authorization', "Bearer {$b['token']}")->getJson('/api/matriculas')->json();
        $this->assertArrayHasKey($b['id'], $mapB);
        $this->assertNull($mapB[$b['id']]['curso_matriculado_id']);
        $this->assertArrayNotHasKey($a['id'], $mapB);
    }
}
