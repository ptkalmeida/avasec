<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\HistoricoEmitido;
use App\Support\Fuso;
use App\Support\Identity;
use App\Support\InstructorScope;
use App\Support\TextoDoDocumento;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use RuntimeException;
use Throwable;

/**
 * Geração do PDF de certificado (dompdf) — separado do CertificateService para
 * não acoplar a regra de emissão às dependências de apresentação (dompdf/QR).
 * QR em SVG (sem exigir ext-gd) apontando para a verificação pública (ADR 09).
 */
final class CertificatePdfService
{
    public function __construct(
        private readonly DocumentTemplateService $templates,
        private readonly UploadService $uploads,
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
        ] + $this->variaveisDoModelo($template, [
            'aluno' => $cert->nome_aluno,
            'curso' => $cert->titulo_curso,
            'carga_horaria' => is_numeric($carga_horaria) ? (int) $carga_horaria.' horas' : null,
            'frequencia' => $cert->percentual_frequencia.'%',
            'data' => $cert->data_emissao?->format('d/m/Y'),
        ]);

        $html_personalizado = $template['html_personalizado'] ?? null;

        return [
            'conteudo' => $this->renderFromVars($vars, is_string($html_personalizado) ? $html_personalizado : null),
            'filename' => "certificado-{$cert->hash_verificacao}.pdf",
        ];
    }

    /**
     * Pré-visualização com dados de exemplo, para o certificado e para o histórico.
     * Sem `$rascunho`, mostra o modelo JÁ SALVO; com ele, o que a pessoa está
     * editando na tela (por cima do salvo), sem gravar nada.
     * Admin-only (o controller já garante o papel).
     *
     * @param  array<string, mixed>|null  $rascunho
     * @return array{conteudo: string, filename: string}
     */
    public function renderPreviewPdf(string $tipo = 'certificado', ?array $rascunho = null): array
    {
        $template = $rascunho === null
            ? $this->templates->get($tipo)
            : array_merge($this->templates->get($tipo), $rascunho);
        // Data local: emitir às 22h BRT datava o documento do dia seguinte.
        $hoje = Fuso::agora()->format('d/m/Y');

        if ($tipo === 'historico') {
            return [
                'conteudo' => $this->renderHistorico($template, 'Aluno(a) Exemplo', $hoje, [
                    ['curso' => 'Curso Modelo de Demonstração', 'carga_horaria' => '40 horas', 'frequencia' => '100%', 'conclusao' => $hoje],
                    ['curso' => 'Outro Curso de Exemplo', 'carga_horaria' => '60 horas', 'frequencia' => '92%', 'conclusao' => $hoje],
                ]),
                'filename' => 'preview-historico.pdf',
            ];
        }

        $verificationUrl = $this->verificationUrl('PREVIEW');
        $vars = [
            'nome_aluno' => 'Aluno(a) Exemplo',
            'titulo_curso' => 'Curso Modelo de Demonstração',
            'carga_horaria' => 40,
            'percentual_frequencia' => 100,
            'data_emissao' => $hoje,
            'hash_verificacao' => 'PREVIEW-'.strtoupper(substr(md5((string) microtime()), 0, 8)),
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $this->buildQrSvgDataUri($verificationUrl),
        ] + $this->variaveisDoModelo($template, [
            'aluno' => 'Aluno(a) Exemplo',
            'curso' => 'Curso Modelo de Demonstração',
            'carga_horaria' => '40 horas',
            'frequencia' => '100%',
            'data' => $hoje,
        ]);

        $html_personalizado = $template['html_personalizado'] ?? null;

        return [
            'conteudo' => $this->renderFromVars($vars, is_string($html_personalizado) ? $html_personalizado : null),
            'filename' => 'preview-certificado.pdf',
        ];
    }

    /**
     * PDF de um histórico JÁ EMITIDO, com os dados fotografados na emissão e o
     * modelo de hoje (cabeçalho, assinaturas, rodapé). O aluno só baixa o próprio;
     * o admin baixa qualquer um; instrutor não baixa.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array{conteudo: string, filename: string}
     */
    public function renderHistoricoEmitido(int $historicoId, array $requester): array
    {
        $historico = HistoricoEmitido::query()->find($historicoId);
        if ($historico === null) {
            throw ApiException::notFound('Histórico não encontrado.');
        }
        $ehDono = Identity::ownsRow($historico->usuario_id, $requester);
        if ($requester['papel'] !== 'admin' && ! ($requester['papel'] === 'student' && $ehDono)) {
            throw ApiException::forbidden('Você só pode baixar o seu próprio histórico escolar.');
        }

        $dados = $historico->dados;
        $data = $historico->emitido_em->setTimezone(Fuso::exibicao())->format('d/m/Y');

        return [
            'conteudo' => $this->renderHistorico($this->templates->get('historico'), $dados['nome_aluno'], $data, $dados['linhas']),
            'filename' => "historico-escolar-{$historico->id}.pdf",
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  list<array{curso: string, carga_horaria: string, frequencia: string, conclusao: string}>  $linhas
     */
    private function renderHistorico(array $template, string $nomeAluno, string $data, array $linhas): string
    {
        $vars = $this->variaveisDoModelo($template, ['aluno' => $nomeAluno, 'data' => $data]) + [
            'rotulos' => array_merge(DocumentTemplateService::ROTULOS_DO_HISTORICO, is_array($template['rotulos_colunas'] ?? null) ? $template['rotulos_colunas'] : []),
            'linhas' => $linhas,
        ];

        return $this->renderView('certificates.historico', $vars);
    }

    /**
     * O que o modelo põe no documento: título, corpo (campos trocados), logo,
     * instituição, assinaturas e rodapé.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, string|null>  $valores
     * @return array<string, mixed>
     */
    private function variaveisDoModelo(array $template, array $valores): array
    {
        $texto = is_string($template['texto_corpo'] ?? null) ? $template['texto_corpo'] : '';
        $logo = is_string($template['caminho_logo_instituicao'] ?? null) ? $template['caminho_logo_instituicao'] : null;

        return [
            'nome_instituicao' => $template['nome_instituicao'],
            'titulo' => is_string($template['titulo'] ?? null) ? $template['titulo'] : '',
            'corpoHtml' => TextoDoDocumento::renderizar($texto, $valores),
            'logoDataUri' => $this->logoComoDataUri($logo),
            'signatarios' => $template['signatarios'],
            'texto_rodape' => $template['texto_rodape'],
        ];
    }

    /**
     * O logo sai do disco público como data URI: o dompdf não busca recurso remoto
     * (`isRemoteEnabled=false`). Só aceita o que o próprio upload gravou.
     */
    private function logoComoDataUri(?string $caminho): ?string
    {
        if ($caminho === null || preg_match('#^/uploads/([A-Za-z0-9._-]+)$#', $caminho, $m) !== 1) {
            return null;
        }

        try {
            $arquivo = $this->uploads->resolvePublicFile($m[1]);
        } catch (ApiException) {
            return null;
        }
        if (! in_array($arquivo['mime'], ['image/png', 'image/jpeg'], true)) {
            return null;
        }
        // Pelo disco, não pelo caminho: vale igual para o disco local e para o S3/MinIO.
        try {
            $bytes = $arquivo['disco']->get($arquivo['chave']);
        } catch (Throwable) {
            $bytes = null;
        }

        return $bytes === null ? null : 'data:'.$arquivo['mime'].';base64,'.base64_encode($bytes);
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function renderView(string $view, array $vars): string
    {
        return Pdf::loadView($view, $vars)
            ->setPaper('a4', 'landscape')
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Serif'])
            ->output();
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
