<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificate;
use App\Models\Course;
use App\Support\Identity;
use App\Support\InstructorScope;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use RuntimeException;

/**
 * Geração do PDF de certificado (dompdf) — separado do CertificateService para
 * não acoplar a regra de emissão às dependências de apresentação (dompdf/QR).
 * QR em SVG (sem exigir ext-gd) apontando para a verificação pública (ADR 09).
 */
final class CertificatePdfService
{
    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array{content: string, filename: string}
     */
    public function renderPdf(string $certificateId, array $requester): array
    {
        $cert = Certificate::query()->find($certificateId);
        if ($cert === null) {
            throw ApiException::notFound('Certificado não encontrado.');
        }
        if ($requester['role'] === 'student' && ! Identity::ownsRow($cert->userId, $requester)) {
            throw ApiException::forbidden('Você só pode baixar o próprio certificado.');
        }
        // Instrutor só baixa PDF de certificado de curso que leciona (antes: qualquer um).
        if ($requester['role'] === 'instructor'
            && ! in_array($cert->courseId, InstructorScope::courseIds($requester), true)) {
            throw ApiException::forbidden('Você só pode baixar certificados dos seus cursos.');
        }

        $verificationUrl = $this->verificationUrl($cert->verificationHash);
        $cargaHoraria = Course::query()->find($cert->courseId)?->cargaHoraria;

        $pdf = Pdf::loadView('certificates.pdf', [
            'studentName' => $cert->studentName,
            'courseTitle' => $cert->courseTitle,
            'cargaHoraria' => is_numeric($cargaHoraria) ? (int) $cargaHoraria : null,
            'attendancePercent' => $cert->attendancePercent,
            // Acesso direto devolve Carbon (cast date) — formata para o Blade.
            'issueDate' => $cert->issueDate?->format('d/m/Y'),
            'verificationHash' => $cert->verificationHash,
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $this->buildQrSvgDataUri($verificationUrl),
        ])
            ->setPaper('a4', 'landscape')
            // isRemoteEnabled=false (default) impede fetch de recursos externos via HTML/CSS.
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Serif']);

        return [
            'content' => $pdf->output(),
            'filename' => "certificado-{$cert->verificationHash}.pdf",
        ];
    }

    private function verificationUrl(string $hash): string
    {
        $base = config('certificates.verification_base_url');
        $base = is_string($base) && $base !== '' ? rtrim($base, '/') : 'http://localhost';

        return "{$base}/?verify={$hash}";
    }

    private function buildQrSvgDataUri(string $verificationUrl): string
    {
        // Defaults do chillerlan/php-qrcode v6: markup SVG + saída base64 (data URI).
        $rendered = (new QRCode)->render($verificationUrl);
        if (! is_string($rendered)) {
            throw new RuntimeException('Falha ao gerar o QR code do certificado.');
        }

        return $rendered;
    }
}
