<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Rename seguro (ADR 10): com identidade por usuario_id, renomear só atualiza o
 * Usuario e os snapshots de exibição — nenhum dado órfão, nenhuma duplicação.
 */
final class RenameUserTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_student_renames_self_and_enrollment_survives(): void
    {
        $student = $this->makeStudent('Aluno Rename');
        $curso_id = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])->assertOk();

        $newName = 'Nome Novo '.uniqid();
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/autenticacao/usuarios/'.$student['id'].'/nome', ['nome' => $newName])
            ->assertOk()
            ->assertJsonPath('nome', $newName);

        // A matrícula continua a MESMA (keyed por id) e o display acompanhou.
        $map = $this->withHeader('Authorization', "Bearer {$student['token']}")->getJson('/api/matriculas')->json();
        $this->assertSame($curso_id, $map[$student['id']]['curso_matriculado_id']);
        $this->assertSame($newName, $map[$student['id']]['nome_aluno']);
    }

    public function test_student_cannot_rename_another_user(): void
    {
        $student = $this->makeStudent('Aluno Rename');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/autenticacao/usuarios/'.$other['id'].'/nome', ['nome' => 'Invasor'])
            ->assertStatus(403);
    }

    public function test_rename_does_not_touch_issued_certificate_name(): void
    {
        $student = $this->makeStudent('Aluno Rename Cert');

        // Curso de 1 aula com 100% para emitir o certificado.
        $curso_id = 'course-ren-'.uniqid();
        $aula_id = 'lesson-ren-'.uniqid();
        DB::table('curso')->insert([
            'id' => $curso_id, 'titulo' => 'Curso Rename', 'descricao' => 'desc', 'categoria' => 'x',
            'miniatura' => 't', 'nome_instrutor' => 'Gestor de Conteúdos',
        ]);
        DB::table('aula')->insert([
            'id' => $aula_id, 'curso_id' => $curso_id, 'titulo' => 'A1', 'duracao' => '5min', 'ordem' => 0,
        ]);
        $this->matricular($student, $curso_id);
        DB::table('progresso_aluno')->insert([
            'id' => 'prog-ren-'.uniqid(), 'nome_aluno' => $student['nome'], 'usuario_id' => $student['id'], 'curso_id' => $curso_id,
            'aulas_concluidas' => json_encode([$aula_id]), 'sessoes_ao_vivo_assistidas' => json_encode([]),
        ]);
        $issued = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['curso_id' => $curso_id]);
        $issued->assertStatus(201);
        $nome_original = $student['nome'];

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/autenticacao/usuarios/'.$student['id'].'/nome', ['nome' => 'Renomeado '.uniqid()])
            ->assertOk();

        // Certificado é documento histórico: mantém o nome impresso na emissão.
        $this->assertDatabaseHas('certificado', [
            'id' => $issued->json('id'),
            'nome_aluno' => $nome_original,
        ]);
    }

    public function test_admin_can_rename_any_user(): void
    {
        $student = $this->makeStudent('Aluno Rename Admin');
        $admin = $this->staffToken('admin');

        $newName = 'Renomeado pelo Admin '.uniqid();
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/autenticacao/usuarios/'.$student['id'].'/nome', ['nome' => $newName])
            ->assertOk()
            ->assertJsonPath('nome', $newName);
    }
}
