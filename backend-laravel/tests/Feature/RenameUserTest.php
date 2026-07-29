<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Rename seguro (ADR 10): com identidade por userId, renomear só atualiza o
 * User e os snapshots de exibição — nenhum dado órfão, nenhuma duplicação.
 */
final class RenameUserTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_student_renames_self_and_enrollment_survives(): void
    {
        $student = $this->makeStudent('Aluno Rename');
        $courseId = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])->assertOk();

        $newName = 'Nome Novo '.uniqid();
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/auth/users/'.$student['id'].'/name', ['name' => $newName])
            ->assertOk()
            ->assertJsonPath('name', $newName);

        // A matrícula continua a MESMA (keyed por id) e o display acompanhou.
        $map = $this->withHeader('Authorization', "Bearer {$student['token']}")->getJson('/api/enrollments')->json();
        $this->assertSame($courseId, $map[$student['id']]['enrolledCourseId']);
        $this->assertSame($newName, $map[$student['id']]['studentName']);
    }

    public function test_student_cannot_rename_another_user(): void
    {
        $student = $this->makeStudent('Aluno Rename');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/auth/users/'.$other['id'].'/name', ['name' => 'Invasor'])
            ->assertStatus(403);
    }

    public function test_rename_does_not_touch_issued_certificate_name(): void
    {
        $student = $this->makeStudent('Aluno Rename Cert');

        // Curso de 1 aula com 100% para emitir o certificado.
        $courseId = 'course-ren-'.uniqid();
        $lessonId = 'lesson-ren-'.uniqid();
        DB::table('Course')->insert([
            'id' => $courseId, 'title' => 'Curso Rename', 'description' => 'desc', 'category' => 'x',
            'thumbnail' => 't', 'instructorName' => 'Gestor de Conteúdos',
        ]);
        DB::table('Lesson')->insert([
            'id' => $lessonId, 'courseId' => $courseId, 'title' => 'A1', 'duration' => '5min', 'lesson_order' => 0,
        ]);
        DB::table('StudentProgress')->insert([
            'id' => 'prog-ren-'.uniqid(), 'studentName' => $student['name'], 'userId' => $student['id'], 'courseId' => $courseId,
            'completedLessons' => json_encode([$lessonId]), 'attendedLiveSessions' => json_encode([]),
        ]);
        $issued = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificates', ['courseId' => $courseId]);
        $issued->assertStatus(201);
        $originalName = $student['name'];

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/auth/users/'.$student['id'].'/name', ['name' => 'Renomeado '.uniqid()])
            ->assertOk();

        // Certificado é documento histórico: mantém o nome impresso na emissão.
        $this->assertDatabaseHas('Certificate', [
            'id' => $issued->json('id'),
            'studentName' => $originalName,
        ]);
    }

    public function test_admin_can_rename_any_user(): void
    {
        $student = $this->makeStudent('Aluno Rename Admin');
        $admin = $this->staffToken('admin');

        $newName = 'Renomeado pelo Admin '.uniqid();
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/auth/users/'.$student['id'].'/name', ['name' => $newName])
            ->assertOk()
            ->assertJsonPath('name', $newName);
    }
}
