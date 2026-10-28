<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Certificados — identidade por usuario_id (ADR 10): emissão do próprio aluno deriva
 * do token; staff informa usuario_id. Requer MySQL de dev populado.
 */
final class CertificateTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_verify_is_public(): void
    {
        // Sem token, deve responder 200 (cert ou null) — nunca 401.
        $this->getJson('/api/certificados/verificar?q=AVA-INEXISTENTE')
            ->assertOk();
    }

    public function test_issue_without_meeting_attendance_is_forbidden(): void
    {
        $student = $this->makeStudent('Aluno Cert');

        // Curso sem progresso registrado para este aluno -> frequência insuficiente.
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['curso_id' => $this->anySeededCourseId()])
            ->assertStatus(403)
            ->assertJsonFragment(['error' => true]);
    }

    public function test_student_cannot_issue_certificate_for_another(): void
    {
        $student = $this->makeStudent('Aluno Cert');
        $other = $this->makeStudent('Outro Aluno');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['usuario_id' => $other['id'], 'curso_id' => $this->anySeededCourseId()])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_certificate(): void
    {
        $student = $this->makeStudent('Aluno Cert');
        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->deleteJson('/api/certificados/qualquer-id')
            ->assertStatus(403);
    }

    public function test_issue_is_idempotent_when_certificate_exists(): void
    {
        $student = $this->makeStudent('Aluno Cert');

        // Curso com uma única aula (frequência 100% com 1 aula concluída).
        $curso_id = 'course-cert-'.uniqid();
        $aula_id = 'lesson-cert-'.uniqid();
        DB::table('curso')->insert([
            'id' => $curso_id, 'titulo' => 'Curso Cert', 'descricao' => 'desc', 'categoria' => 'x',
            'miniatura' => 't', 'nome_instrutor' => 'Gestor de Conteúdos',
        ]);
        DB::table('aula')->insert([
            'id' => $aula_id, 'curso_id' => $curso_id, 'titulo' => 'A1', 'duracao' => '5min', 'ordem' => 0,
        ]);
        $this->matricular($student, $curso_id);
        DB::table('progresso_aluno')->insert([
            'id' => 'prog-cert-'.uniqid(), 'nome_aluno' => $student['nome'], 'usuario_id' => $student['id'], 'curso_id' => $curso_id,
            'aulas_concluidas' => json_encode([$aula_id]), 'sessoes_ao_vivo_assistidas' => json_encode([]),
        ]);

        $first = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['curso_id' => $curso_id]);
        $first->assertStatus(201)
            ->assertJsonPath('percentual_frequencia', 100)
            ->assertJsonPath('usuario_id', $student['id'])
            ->assertJsonPath('nome_aluno', $student['nome']);
        $firstId = $first->json('id');

        // Segunda emissão retorna o MESMO certificado (idempotente por usuario_id+curso_id).
        $second = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['curso_id' => $curso_id]);
        $second->assertStatus(201)->assertJsonPath('id', $firstId);
    }
}
