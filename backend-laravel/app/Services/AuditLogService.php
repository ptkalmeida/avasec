<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
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

    /**
     * O expurgo da trilha de auditoria NÃO existe mais (ADR 12).
     *
     * Isto fazia `SecurityLog::query()->delete()` — apagava a trilha inteira,
     * inclusive o registro de quem a apagou. Uma trilha que se apaga a si mesma
     * não serve de auditoria: bastava um clique para sumir com a evidência de
     * qualquer ação anterior.
     *
     * Se algum dia houver exigência legal de expurgo (LGPD, retenção), será
     * procedimento administrativo documentado com autorização humana
     * registrada — não um botão de tela.
     */
    public function clearSecurityLogs(): void
    {
        throw ApiException::forbidden(
            'A trilha de auditoria não pode ser apagada. Registro de segurança é permanente (ADR 12).'
        );
    }

    /**
     * @param  array{action:string,details:string,status?:string|null}  $input
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
