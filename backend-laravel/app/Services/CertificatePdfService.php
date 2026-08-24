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
    public function __construct(
        private readonly DocumentTemplateService $templates,
    ) {}

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
        $template = $this->templates->get('certificado');

        $vars = [
            'studentName' => $cert->studentName,
            'courseTitle' => $cert->courseTitle,
            'cargaHoraria' => is_numeric($cargaHoraria) ? (int) $cargaHoraria : null,
            'attendancePercent' => $cert->attendancePercent,
            // Acesso direto devolve Carbon (cast date) — formata para o Blade.
            'issueDate' => $cert->issueDate?->format('d/m/Y'),
            'verificationHash' => $cert->verificationHash,
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $this->buildQrSvgDataUri($verificationUrl),
            'institutionName' => $template['institutionName'],
            'signatories' => $template['signatories'],
            'footerText' => $template['footerText'],
        ];

        $customHtml = $template['customHtml'] ?? null;

        return [
            'content' => $this->renderFromVars($vars, is_string($customHtml) ? $customHtml : null),
            'filename' => "certificado-{$cert->verificationHash}.pdf",
        ];
    }

    /**
     * Pré-visualização do template ATUALMENTE SALVO (não o rascunho em edição na
     * tela do Admin) com dados de exemplo — só para o tipo 'certificado', o único
     * com pipeline de PDF hoje. Admin-only (o controller já garante o papel).
     *
     * @return array{content: string, filename: string}
     */
    public function renderPreviewPdf(): array
    {
        $template = $this->templates->get('certificado');
        $verificationUrl = $this->verificationUrl('PREVIEW');

        $vars = [
            'studentName' => 'Aluno(a) Exemplo',
            'courseTitle' => 'Curso Modelo de Demonstração',
            'cargaHoraria' => 40,
            'attendancePercent' => 100,
            'issueDate' => now()->format('d/m/Y'),
            'verificationHash' => 'PREVIEW-'.strtoupper(substr(md5((string) microtime()), 0, 8)),
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $this->buildQrSvgDataUri($verificationUrl),
            'institutionName' => $template['institutionName'],
            'signatories' => $template['signatories'],
            'footerText' => $template['footerText'],
        ];

        $customHtml = $template['customHtml'] ?? null;

        return [
            'content' => $this->renderFromVars($vars, is_string($customHtml) ? $customHtml : null),
            'filename' => 'preview-certificado.pdf',
        ];
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function renderFromVars(array $vars, ?string $customHtml): string
    {
        $pdf = is_string($customHtml) && trim($customHtml) !== ''
            ? Pdf::loadHTML($this->renderCustomHtml($customHtml, $vars))
            : Pdf::loadView('certificates.pdf', $vars);

        $pdf->setPaper('a4', 'landscape')
            // isRemoteEnabled=false (default) impede fetch de recursos externos via HTML/CSS.
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Serif']);

        return $pdf->output();
    }

    /**
     * Modo "layout livre": o HTML vem de um campo editável pelo Admin Superior
     * (DocumentTemplate.customHtml), então NUNCA passa por Blade::render() —
     * isso executaria diretivas PHP arbitrárias armazenadas no banco. Em vez
     * disso, os placeholders documentados na tela de edição são substituídos
     * por texto puro (str_replace), o que é inerte.
     *
     * @param  array<string, mixed>  $vars
     */
    private function renderCustomHtml(string $html, array $vars): string
    {
        $search = [];
        $replace = [];
        foreach ($vars as $key => $value) {
            if ($key === 'signatories' || $value === null) {
                continue;
            }
            $search[] = '{{'.$key.'}}';
            $replace[] = is_scalar($value) ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') : '';
        }
        $qrDataUri = $vars['qrDataUri'] ?? '';
        $search[] = '{{qrImg}}';
        $replace[] = '<img src="'.htmlspecialchars(is_string($qrDataUri) ? $qrDataUri : '', ENT_QUOTES, 'UTF-8').'" width="76" height="76" alt="QR de verificação">';

        return str_replace($search, $replace, $html);
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
