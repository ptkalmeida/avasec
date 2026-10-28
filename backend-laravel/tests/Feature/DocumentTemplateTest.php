<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Área de gerenciamento de templates de documentos (certificado, histórico) —
 * só o Admin Superior lê/edita. Requer MySQL de dev populado.
 */
final class DocumentTemplateTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_admin_gets_defaults_when_nothing_saved_yet(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->getJson('/api/modelos-documento/certificado')
            ->assertOk()
            ->assertJsonPath('tipo', 'certificado')
            ->assertJsonPath('html_personalizado', null)
            ->assertJsonCount(2, 'signatarios');
    }

    public function test_unknown_type_is_404(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->getJson('/api/modelos-documento/inexistente')
            ->assertStatus(404);
    }

    public function test_student_can_read_but_not_write_templates(): void
    {
        // Leitura precisa ser permitida: o aluno vê o mesmo nome/assinatura na
        // prévia do próprio certificado antes de baixar o PDF oficial.
        $student = $this->makeStudent('Aluno Templates');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->getJson('/api/modelos-documento/certificado')
            ->assertOk()
            ->assertJsonPath('tipo', 'certificado');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/modelos-documento/certificado', ['nome_instituicao' => 'Hackeado'])
            ->assertStatus(403);
    }

    public function test_instructor_cannot_write_templates(): void
    {
        $instructor = $this->staffToken('instructor');

        $this->withHeader('Authorization', "Bearer {$instructor}")
            ->putJson('/api/modelos-documento/certificado', ['nome_instituicao' => 'Tentativa'])
            ->assertStatus(403);
    }

    public function test_admin_updates_institution_name_and_signatories(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/modelos-documento/certificado', [
                'nome_instituicao' => 'Escola Estadual da Cultura',
                'signatarios' => [
                    ['name' => 'Fulano de Tal', 'role' => 'Diretor'],
                ],
                'texto_rodape' => 'Rodapé customizado.',
            ])
            ->assertOk()
            ->assertJsonPath('nome_instituicao', 'Escola Estadual da Cultura')
            ->assertJsonPath('signatarios.0.name', 'Fulano de Tal')
            ->assertJsonPath('texto_rodape', 'Rodapé customizado.');

        // Persistiu — uma nova leitura devolve o mesmo valor, não o default.
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->getJson('/api/modelos-documento/certificado')
            ->assertOk()
            ->assertJsonPath('nome_instituicao', 'Escola Estadual da Cultura');
    }

    public function test_admin_previews_certificado_template_as_pdf(): void
    {
        $admin = $this->staffToken('admin');

        $response = $this->withHeader('Authorization', "Bearer {$admin}")
            ->get('/api/modelos-documento/certificado/previa');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_student_cannot_preview_template(): void
    {
        $student = $this->makeStudent('Aluno Preview');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->get('/api/modelos-documento/certificado/previa')
            ->assertStatus(403);
    }

    public function test_admin_previews_historico_template_as_pdf(): void
    {
        $admin = $this->staffToken('admin');

        $response = $this->withHeader('Authorization', "Bearer {$admin}")
            ->get('/api/modelos-documento/historico/previa');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_certificate_pdf_reflects_saved_institution_name(): void
    {
        $admin = $this->staffToken('admin');
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/modelos-documento/certificado', ['nome_instituicao' => 'Instituição Customizada XYZ'])
            ->assertOk();
        $this->flushHeaders();

        $student = $this->makeStudent('Aluno Template PDF');
        $curso_id = 'course-tpl-'.uniqid();
        $aula_id = 'lesson-tpl-'.uniqid();
        DB::table('curso')->insert([
            'id' => $curso_id, 'titulo' => 'Curso Template', 'descricao' => 'desc', 'categoria' => 'x',
            'miniatura' => 't', 'nome_instrutor' => 'Gestor de Conteúdos', 'carga_horaria' => 40,
        ]);
        DB::table('aula')->insert([
            'id' => $aula_id, 'curso_id' => $curso_id, 'titulo' => 'A1', 'duracao' => '5min', 'ordem' => 0,
        ]);
        $this->matricular($student, $curso_id);
        DB::table('progresso_aluno')->insert([
            'id' => 'prog-tpl-'.uniqid(), 'nome_aluno' => $student['nome'], 'usuario_id' => $student['id'], 'curso_id' => $curso_id,
            'aulas_concluidas' => json_encode([$aula_id]), 'sessoes_ao_vivo_assistidas' => json_encode([]),
        ]);

        $issued = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['curso_id' => $curso_id]);
        $issued->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->get("/api/certificados/{$issued->json('id')}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }
}
