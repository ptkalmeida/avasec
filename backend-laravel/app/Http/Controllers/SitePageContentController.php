<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\AuditLogger;
use App\Services\SitePageContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conteúdo editável das páginas públicas. Leitura é pública (o site anônimo
 * monta as páginas com isto); escrita é exclusiva do Admin Superior.
 */
final class SitePageContentController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly SitePageContentService $pages,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Conteúdo de todas as páginas + o schema de campos, numa só chamada.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'pages' => $this->pages->all(),
            'schema' => $this->pages->schema(),
        ]);
    }

    public function show(string $pageKey): JsonResponse
    {
        return response()->json($this->pages->get($pageKey));
    }

    public function update(Request $request, string $pageKey): JsonResponse
    {
        // O shape exato de cada página é validado no service, contra o SCHEMA:
        // aqui só garantimos os tipos de topo antes de repassar.
        $this->validateInput($request, [
            'items' => ['sometimes', 'array', 'max:60'],
        ]);

        $updated = $this->pages->upsert($pageKey, $request->all(), $this->requester($request));
        $this->audit->log($request, 'Edição de Conteúdo do Site', "Página \"{$pageKey}\" foi atualizada.");

        return response()->json($updated);
    }
}
