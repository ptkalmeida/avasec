<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificado;
use App\Models\Curso;
use App\Support\Fuso;
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
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array{conteudo: string, filename: string}
     */
    public function renderPdf(string $certificateId, array $requester): array
    {
        $cert = Certificado::query()->find($certificateId);
        if ($cert === null) {
            throw ApiException::notFound('Certificado não encontrado.');
        }
        if ($requester['papel'] === 'student' && ! Identity::ownsRow($cert->usuario_id, $requester)) {
            throw ApiException::forbidden('Você só pode baixar o próprio certificado.');
        }
        // Instrutor só baixa PDF de certificado de curso que leciona (antes: qualquer um).
        if ($requester['papel'] === 'instructor'
            && ! in_array($cert->curso_id, InstructorScope::courseIds($requester), true)) {
            throw ApiException::forbidden('Você só pode baixar certificados dos seus cursos.');
        }

        $verificationUrl = $this->verificationUrl($cert->hash_verificacao);
        $carga_horaria = Curso::query()->find($cert->curso_id)?->carga_horaria;
        $template = $this->templates->get('certificado');

        $vars = [
            'nome_aluno' => $cert->nome_aluno,
            'titulo_curso' => $cert->titulo_curso,
            'carga_horaria' => is_numeric($carga_horaria) ? (int) $carga_horaria : null,
            'percentual_frequencia' => $cert->percentual_frequencia,
            // Acesso direto devolve Carbon (cast date) — formata para o Blade.
            'data_emissao' => $cert->data_emissao?->format('d/m/Y'),
            'hash_verificacao' => $cert->hash_verificacao,
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $this->buildQrSvgDataUri($verificationUrl),
            'nome_instituicao' => $template['nome_instituicao'],
            'signatarios' => $template['signatarios'],
            'texto_rodape' => $template['texto_rodape'],
        ];

        $html_personalizado = $template['html_personalizado'] ?? null;

        return [
            'conteudo' => $this->renderFromVars($vars, is_string($html_personalizado) ? $html_personalizado : null),
            'filename' => "certificado-{$cert->hash_verificacao}.pdf",
        ];
    }

    /**
     * Pré-visualização do template ATUALMENTE SALVO (não o rascunho em edição na
     * tela do Admin) com dados de exemplo — só para o tipo 'certificado', o único
     * com pipeline de PDF hoje. Admin-only (o controller já garante o papel).
     *
     * @return array{conteudo: string, filename: string}
     */
    public function renderPreviewPdf(): array
    {
        $template = $this->templates->get('certificado');
        $verificationUrl = $this->verificationUrl('PREVIEW');

        $vars = [
            'nome_aluno' => 'Aluno(a) Exemplo',
            'titulo_curso' => 'Curso Modelo de Demonstração',
            'carga_horaria' => 40,
            'percentual_frequencia' => 100,
            // Data local: emitir às 22h BRT datava o certificado do dia seguinte.
            'data_emissao' => Fuso::agora()->format('d/m/Y'),
            'hash_verificacao' => 'PREVIEW-'.strtoupper(substr(md5((string) microtime()), 0, 8)),
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $this->buildQrSvgDataUri($verificationUrl),
            'nome_instituicao' => $template['nome_instituicao'],
            'signatarios' => $template['signatarios'],
            'texto_rodape' => $template['texto_rodape'],
        ];

        $html_personalizado = $template['html_personalizado'] ?? null;

        return [
            'conteudo' => $this->renderFromVars($vars, is_string($html_personalizado) ? $html_personalizado : null),
            'filename' => 'preview-certificado.pdf',
        ];
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function renderFromVars(array $vars, ?string $html_personalizado): string
    {
        $pdf = is_string($html_personalizado) && trim($html_personalizado) !== ''
            ? Pdf::loadHTML($this->renderCustomHtml($html_personalizado, $vars))
            : Pdf::loadView('certificates.pdf', $vars);

        $pdf->setPaper('a4', 'landscape')
            // isRemoteEnabled=false (default) impede fetch de recursos externos via HTML/CSS.
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Serif']);

        return $pdf->output();
    }

    /**
     * Modo "layout livre": o HTML vem de um campo editável pelo Admin Superior
     * (ModeloDocumento.html_personalizado), então NUNCA passa por Blade::render() —
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
            if ($key === 'signatarios' || $value === null) {
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
