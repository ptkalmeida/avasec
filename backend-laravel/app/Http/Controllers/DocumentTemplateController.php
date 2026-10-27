<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\AuditLogger;
use App\Services\CertificatePdfService;
use App\Services\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DocumentTemplateController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly DocumentTemplateService $templates,
        private readonly CertificatePdfService $certificatePdf,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, string $type): JsonResponse
    {
        return response()->json($this->templates->get($type));
    }

    /**
     * Pré-visualização em PDF do template ATUALMENTE SALVO, com dados de
     * exemplo — só existe pipeline de PDF para 'certificado' hoje.
     */
    public function preview(Request $request, string $type): Response
    {
        if (! in_array($type, DocumentTemplateService::TYPES, true)) {
            throw ApiException::notFound('Tipo de documento desconhecido.');
        }

        $result = $this->certificatePdf->renderPreviewPdf($type);
        $this->audit->log($request, 'Pré-visualização de Template', "Template \"{$type}\" pré-visualizado.");

        return response($result['conteudo'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$result['filename']}\"",
        ]);
    }

    public function update(Request $request, string $type): JsonResponse
    {
        $data = $this->validateInput($request, [
            'nome_instituicao' => ['sometimes', 'nullable', 'string', 'max:191'],
            'caminho_logo_instituicao' => ['sometimes', 'nullable', 'string', 'max:191'],
            'signatarios' => ['sometimes', 'array', 'max:10'],
            'signatarios.*.name' => ['required_with:signatarios', 'string', 'max:191'],
            'signatarios.*.role' => ['required_with:signatarios', 'string', 'max:191'],
            'texto_rodape' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'html_personalizado' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'titulo' => ['sometimes', 'nullable', 'string', 'max:191'],
            'texto_corpo' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'rotulos_colunas' => ['sometimes', 'nullable', 'array'],
            'rotulos_colunas.*' => ['nullable', 'string', 'max:60'],
        ]);

        $updates = [];
        foreach (['nome_instituicao', 'caminho_logo_instituicao', 'texto_rodape', 'html_personalizado', 'titulo', 'texto_corpo'] as $key) {
            if (array_key_exists($key, $data)) {
                $updates[$key] = $this->optionalString($data, $key);
            }
        }
        if (array_key_exists('rotulos_colunas', $data)) {
            $updates['rotulos_colunas'] = is_array($data['rotulos_colunas'])
                ? array_intersect_key(array_filter($data['rotulos_colunas'], 'is_string'), DocumentTemplateService::ROTULOS_DO_HISTORICO)
                : null;
        }
        if (array_key_exists('signatarios', $data)) {
            $updates['signatarios'] = $this->signatoriesList($data);
        }

        $updated = $this->templates->upsert($type, $updates, $this->requester($request));
        $this->audit->log($request, 'Edição de Template de Documento', "Template \"{$type}\" foi atualizado.");

        return response()->json($updated);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{name: string, role: string}>
     */
    private function signatoriesList(array $data): array
    {
        $raw = $data['signatarios'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $list = [];
        foreach ($raw as $item) {
            if (is_array($item) && is_string($item['name'] ?? null) && is_string($item['role'] ?? null)) {
                $list[] = ['name' => $item['name'], 'role' => $item['role']];
            }
        }

        return $list;
    }
}
