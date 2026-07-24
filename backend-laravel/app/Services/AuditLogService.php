<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientEvent;
use App\Models\SecurityLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Leitura da trilha de AUDITORIA (SecurityLog, gravada só pelo servidor) e
 * gravação/leitura de TELEMETRIA (ClientEvent, eventos de UI do frontend) —
 * espelha src/server/services/auditLogService.ts. São tabelas distintas: a
 * telemetria nunca se mistura à auditoria.
 */
final class AuditLogService
{
    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public function listSecurityLogs(int $skip, int $take): array
    {
        $total = SecurityLog::query()->count();
        $items = SecurityLog::query()->orderBy('timestamp', 'desc')->skip($skip)->take($take)->get()->map->toArray()->all();

        return ['items' => $items, 'total' => $total];
    }

    public function clearSecurityLogs(): void
    {
        SecurityLog::query()->delete();
    }

    /**
     * @param  array{action:string,details:string,status?:string}  $input
     * @param  array{name:string,role:string}  $actor
     * @return array<string, mixed>
     */
    public function recordClientEvent(array $input, array $actor, string $ipAddress, string $device): array
    {
        return ClientEvent::query()->create([
            'id' => (string) Str::uuid(),
            'createdAt' => CarbonImmutable::now(),
            'user' => $actor['name'],
            'role' => $actor['role'],
            'ipAddress' => $ipAddress,
            'device' => Str::substr($device, 0, 190),
            'action' => Str::substr($input['action'], 0, 190),
            'details' => Str::substr($input['details'], 0, 1000),
            'status' => $input['status'] ?? 'SUCCESS',
        ])->toArray();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public function listClientEvents(int $skip, int $take): array
    {
        $total = ClientEvent::query()->count();
        $items = ClientEvent::query()->orderBy('createdAt', 'desc')->skip($skip)->take($take)->get()->map->toArray()->all();

        return ['items' => $items, 'total' => $total];
    }
}
