<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PDF de certificado (ADR 09) — download autorizado (dono ou staff) e sanitização
 * da verificação pública. Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class CertificatePdfTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Cria aluno + curso de 1 aula com progresso 100% e emite o certificado.
     *
     * @return array{certId: string, hash: string, studentToken: string, courseId: string}
     */
    private function issueCertificateForNewStudent(): array
    {
        $adminRow = DB::table('User')->where('role', 'admin')->first();
        $admin = Jwt::issue($adminRow->id, $adminRow->name, 'admin');

        $name = 'Aluno PDF '.uniqid();
        $reg = $this->withHeader('Authorization', "Bearer $admin")->postJson('/api/auth/register', [
            'name' => $name, 'email' => 'pdf-'.uniqid().'@example.com', 'password' => 'senha123456', 'role' => 'student',
        ]);
        $studentId = $reg->json('user.id');
        $studentToken = Jwt::issue($studentId, $name, 'student');

        $courseId = 'course-pdf-'.uniqid();
        $lessonId = 'lesson-pdf-'.uniqid();
        DB::table('Course')->insert([
            'id' => $courseId, 'title' => 'Curso PDF', 'description' => 'desc', 'category' => 'x',
            'thumbnail' => 't', 'instructorName' => 'Gestor de Conteúdos', 'cargaHoraria' => 40,
        ]);
        DB::table('Lesson')->insert([
            'id' => $lessonId, 'courseId' => $courseId, 'title' => 'A1', 'duration' => '5min', 'lesson_order' => 0,
        ]);
        DB::table('StudentProgress')->insert([
            'id' => 'prog-pdf-'.uniqid(), 'studentName' => $name, 'userId' => $studentId, 'courseId' => $courseId,
            'completedLessons' => json_encode([$lessonId]), 'attendedLiveSessions' => json_encode([]),
        ]);

        $issued = $this->withHeader('Authorization', "Bearer $studentToken")
            ->postJson('/api/certificates', ['studentName' => $name, 'courseId' => $courseId]);
        $issued->assertStatus(201);

        return [
            'certId' => $issued->json('id'),
            'hash' => $issued->json('verificationHash'),
            'studentToken' => $studentToken,
            'courseId' => $courseId,
        ];
    }

    public function test_owner_downloads_pdf(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $response = $this->withHeader('Authorization', "Bearer {$ctx['studentToken']}")
            ->get("/api/certificates/{$ctx['certId']}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString($ctx['hash'], (string) $response->headers->get('content-disposition'));
        // Sem golden file: dompdf não é determinístico byte a byte — validamos os magic bytes.
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_other_student_cannot_download_pdf(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $other = DB::table('User')->where('role', 'student')->where('status', 'active')->first(['id', 'name']);
        $otherToken = Jwt::issue($other->id, $other->name, 'student');

        $this->withHeader('Authorization', "Bearer $otherToken")
            ->get("/api/certificates/{$ctx['certId']}/pdf")
            ->assertStatus(403);
    }

    public function test_pdf_of_unknown_certificate_is_404(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $this->withHeader('Authorization', "Bearer {$ctx['studentToken']}")
            ->get('/api/certificates/cert-inexistente/pdf')
            ->assertStatus(404);
    }

    public function test_pdf_requires_authentication(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        // withHeader() do setup persiste nos requests seguintes — limpa antes do anônimo.
        $this->flushHeaders();
        $this->get("/api/certificates/{$ctx['certId']}/pdf")->assertStatus(401);
    }

    public function test_public_verify_is_sanitized_and_includes_carga_horaria(): void
    {
        $ctx = $this->issueCertificateForNewStudent();

        $response = $this->getJson('/api/certificates/verify?q='.$ctx['hash']);
        $response->assertOk()
            ->assertJsonPath('verificationHash', $ctx['hash'])
            ->assertJsonPath('cargaHoraria', 40);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('userId', $payload);
        $this->assertArrayNotHasKey('enrollmentId', $payload);
    }
}
