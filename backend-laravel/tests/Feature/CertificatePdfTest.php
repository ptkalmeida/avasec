<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\ComVerificacaoPublicaLigada;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * PDF de certificado (ADR 09) — download autorizado (dono ou staff) e sanitização
 * da verificação pública. Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class CertificatePdfTest extends TestCase
{
    use ComVerificacaoPublicaLigada;
    use DatabaseTransactions;
    use SeedsIdentity;

    /**
     * Cria aluno + curso de 1 aula com progresso 100% e emite o certificado.
     *
     * @return array{certId: string, hash: string, studentToken: string, curso_id: string}
     */
    private function issueCertificateForNewStudent(): array
    {
        $student = $this->makeStudent('Aluno PDF');

        $curso_id = 'course-pdf-'.uniqid();
        $aula_id = 'lesson-pdf-'.uniqid();
        DB::table('curso')->insert([
            'id' => $curso_id, 'titulo' => 'Curso PDF', 'descricao' => 'desc', 'categoria' => 'x',
            'miniatura' => 't', 'nome_instrutor' => 'Gestor de Conteúdos', 'carga_horaria' => 40,
        ]);
        DB::table('aula')->insert([
            'id' => $aula_id, 'curso_id' => $curso_id, 'titulo' => 'A1', 'duracao' => '5min', 'ordem' => 0,
        ]);
        $this->matricular($student, $curso_id);
        DB::table('progresso_aluno')->insert([
            'id' => 'prog-pdf-'.uniqid(), 'nome_aluno' => $student['nome'], 'usuario_id' => $student['id'], 'curso_id' => $curso_id,
            'aulas_concluidas' => json_encode([$aula_id]), 'sessoes_ao_vivo_assistidas' => json_encode([]),
        ]);

        $issued = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['curso_id' => $curso_id]);
        $issued->assertStatus(201);

        return [
            'certId' => $issued->json('id'),
            'hash' => $issued->json('hash_verificacao'),
            'studentToken' => $student['token'],
            'curso_id' => $curso_id,
        ];
    }

    public function test_owner_downloads_pdf(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $response = $this->withHeader('Authorization', "Bearer {$ctx['studentToken']}")
            ->get("/api/certificados/{$ctx['certId']}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString($ctx['hash'], (string) $response->headers->get('content-disposition'));
        // Sem golden file: dompdf não é determinístico byte a byte — validamos os magic bytes.
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_other_student_cannot_download_pdf(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $other = $this->makeStudent('Outro Aluno PDF');

        $this->withHeader('Authorization', "Bearer {$other['token']}")
            ->get("/api/certificados/{$ctx['certId']}/pdf")
            ->assertStatus(403);
    }

    public function test_pdf_of_unknown_certificate_is_404(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $this->withHeader('Authorization', "Bearer {$ctx['studentToken']}")
            ->get('/api/certificados/cert-inexistente/pdf')
            ->assertStatus(404);
    }

    public function test_pdf_requires_authentication(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        // withHeader() do setup persiste nos requests seguintes — limpa antes do anônimo.
        $this->flushHeaders();
        $this->get("/api/certificados/{$ctx['certId']}/pdf")->assertStatus(401);
    }

    public function test_public_verify_is_sanitized_and_includes_carga_horaria(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $response = $this->getJson('/api/certificados/verificar?q='.$ctx['hash']);
        $response->assertOk()
            ->assertJsonPath('hash_verificacao', $ctx['hash'])
            ->assertJsonPath('carga_horaria', 40);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('usuario_id', $payload);
        $this->assertArrayNotHasKey('matricula_id', $payload);
    }
}
