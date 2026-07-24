<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\AuditLogger;
use App\Services\ExportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $export,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, string $dataset): JsonResponse
    {
        if (! in_array($dataset, ExportService::EXPORTABLE, true)) {
            throw new ApiException(400, 'BAD_REQUEST', 'Base inválida. Bases disponíveis: '.implode(', ', ExportService::EXPORTABLE).'.');
        }

        $data = $this->export->exportDataset($dataset);
        $this->audit->log($request, 'Exportação de Dados Gerenciais', "Base \"{$dataset}\" exportada (".count($data).' registros).');

        return response()->json([
            'dataset' => $dataset,
            'exportedAt' => CarbonImmutable::now()->toIso8601String(),
            'count' => count($data),
            'data' => $data,
        ]);
    }
}
