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
            ->getJson('/api/document-templates/certificado')
            ->assertOk()
            ->assertJsonPath('type', 'certificado')
            ->assertJsonPath('customHtml', null)
            ->assertJsonCount(2, 'signatories');
    }

    public function test_unknown_type_is_404(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->getJson('/api/document-templates/inexistente')
            ->assertStatus(404);
    }

    public function test_student_can_read_but_not_write_templates(): void
    {
        // Leitura precisa ser permitida: o aluno vê o mesmo nome/assinatura na
        // prévia do próprio certificado antes de baixar o PDF oficial.
        $student = $this->makeStudent('Aluno Templates');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->getJson('/api/document-templates/certificado')
            ->assertOk()
            ->assertJsonPath('type', 'certificado');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/document-templates/certificado', ['institutionName' => 'Hackeado'])
            ->assertStatus(403);
    }

    public function test_instructor_cannot_write_templates(): void
    {
        $instructor = $this->staffToken('instructor');

        $this->withHeader('Authorization', "Bearer {$instructor}")
            ->putJson('/api/document-templates/certificado', ['institutionName' => 'Tentativa'])
            ->assertStatus(403);
    }

    public function test_admin_updates_institution_name_and_signatories(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/document-templates/certificado', [
                'institutionName' => 'Escola Estadual da Cultura',
                'signatories' => [
                    ['name' => 'Fulano de Tal', 'role' => 'Diretor'],
                ],
                'footerText' => 'Rodapé customizado.',
            ])
            ->assertOk()
            ->assertJsonPath('institutionName', 'Escola Estadual da Cultura')
            ->assertJsonPath('signatories.0.name', 'Fulano de Tal')
            ->assertJsonPath('footerText', 'Rodapé customizado.');

        // Persistiu — uma nova leitura devolve o mesmo valor, não o default.
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->getJson('/api/document-templates/certificado')
            ->assertOk()
            ->assertJsonPath('institutionName', 'Escola Estadual da Cultura');
    }

    public function test_certificate_pdf_reflects_saved_institution_name(): void
    {
        $admin = $this->staffToken('admin');
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/document-templates/certificado', ['institutionName' => 'Instituição Customizada XYZ'])
            ->assertOk();
        $this->flushHeaders();

        $student = $this->makeStudent('Aluno Template PDF');
        $courseId = 'course-tpl-'.uniqid();
        $lessonId = 'lesson-tpl-'.uniqid();
        DB::table('Course')->insert([
            'id' => $courseId, 'title' => 'Curso Template', 'description' => 'desc', 'category' => 'x',
            'thumbnail' => 't', 'instructorName' => 'Gestor de Conteúdos', 'cargaHoraria' => 40,
        ]);
        DB::table('Lesson')->insert([
            'id' => $lessonId, 'courseId' => $courseId, 'title' => 'A1', 'duration' => '5min', 'lesson_order' => 0,
        ]);
        DB::table('StudentProgress')->insert([
            'id' => 'prog-tpl-'.uniqid(), 'studentName' => $student['name'], 'userId' => $student['id'], 'courseId' => $courseId,
            'completedLessons' => json_encode([$lessonId]), 'attendedLiveSessions' => json_encode([]),
        ]);

        $issued = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificates', ['courseId' => $courseId]);
        $issued->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->get("/api/certificates/{$issued->json('id')}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }
}
