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
        if ($type !== 'certificado') {
            throw ApiException::validation('Pré-visualização em PDF ainda não disponível para este tipo de documento.');
        }

        $result = $this->certificatePdf->renderPreviewPdf();
        $this->audit->log($request, 'Pré-visualização de Template', "Template \"{$type}\" pré-visualizado.");

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$result['filename']}\"",
        ]);
    }

    public function update(Request $request, string $type): JsonResponse
    {
        $data = $this->validateInput($request, [
            'institutionName' => ['sometimes', 'nullable', 'string', 'max:191'],
            'institutionLogoPath' => ['sometimes', 'nullable', 'string', 'max:191'],
            'signatories' => ['sometimes', 'array', 'max:10'],
            'signatories.*.name' => ['required_with:signatories', 'string', 'max:191'],
            'signatories.*.role' => ['required_with:signatories', 'string', 'max:191'],
            'footerText' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'customHtml' => ['sometimes', 'nullable', 'string', 'max:100000'],
        ]);

        $updates = [];
        foreach (['institutionName', 'institutionLogoPath', 'footerText', 'customHtml'] as $key) {
            if (array_key_exists($key, $data)) {
                $updates[$key] = $this->optionalString($data, $key);
            }
        }
        if (array_key_exists('signatories', $data)) {
            $updates['signatories'] = $this->signatoriesList($data);
        }

        $updated = $this->templates->upsert($type, $updates, $this->requester($request));
        $this->audit->log($request, 'Edição de Template de Documento', "Template \"{$type}\" foi atualizado.");

        return response()->json($updated);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{name:string, role:string}>
     */
    private function signatoriesList(array $data): array
    {
        $raw = $data['signatories'] ?? [];
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
