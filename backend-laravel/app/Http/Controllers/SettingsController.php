<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json($this->settings->get());
    }

    public function update(Request $request): JsonResponse
    {
        $updates = $request->all();
        $updated = $this->settings->update($updates);
        $this->audit->log($request, 'Alteração de Configurações do Sistema', 'Configurações atualizadas: '.implode(', ', array_keys($updates)).'.');

        return response()->json($updated);
    }
}
