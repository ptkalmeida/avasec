<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController extends Controller
{
    /**
     * Rota de saúde da Etapa 0 da migração para Laravel.
     * Confirma que o processo sobe e que consegue conectar no MySQL
     * compartilhado com o Prisma/Node (sem tocar no schema).
     */
    public function show(): JsonResponse
    {
        $database = 'ok';
        $status = 200;

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $database = 'unavailable';
            $status = 503;
        }

        return response()->json([
            'service' => 'avasec-laravel',
            'status' => $status === 200 ? 'ok' : 'degraded',
            'database' => $database,
        ], $status);
    }
}
