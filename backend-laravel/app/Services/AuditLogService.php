<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\EventoCliente;
use App\Models\LogSeguranca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Leitura da trilha de AUDITORIA (LogSeguranca, gravada só pelo servidor) e
 * gravação/leitura de TELEMETRIA (EventoCliente, eventos de UI do frontend) —
 * espelha src/server/services/auditLogService.ts. São tabelas distintas: a
 * telemetria nunca se mistura à auditoria.
 */
final class AuditLogService
{
    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public function listSecurityLogs(int $skip, int $take): array
    {
        $total = LogSeguranca::query()->count();
        /*
         * Ordena por `ocorrido_em` (DATETIME), nao por `timestamp` (texto).
         * O texto e "HH:MM:SS DD/MM/AAAA": ordenar por ele e ordenar pela HORA
         * antes da data, entao um registro das 23h de ontem vinha depois de um
         * das 08h de hoje. O "mais recente" da tela estava errado (24/09/2026).
         *
         * Linha sem `ocorrido_em` (texto nao reconhecivel) cai para o fim: no
         * MySQL, NULL ordena por ultimo em DESC. Tratar como a mais antiga e
         * honesto — melhor que inventar uma data para ela.
         */
        $items = LogSeguranca::query()
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id')
            ->skip($skip)->take($take)->get()->map->toArray()->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * O expurgo da trilha de auditoria NÃO existe mais (ADR 12).
     *
     * Isto fazia `LogSeguranca::query()->delete()` — apagava a trilha inteira,
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
     * @param  array{acao:string,detalhes:string,status?:string|null}  $input
     * @param  array{nome:string,papel:string}  $actor
     * @return array<string, mixed>
     */
    public function recordClientEvent(array $input, array $actor, string $ip, string $device): array
    {
        return EventoCliente::query()->create([
            'id' => (string) Str::uuid(),
            'criado_em' => CarbonImmutable::now(),
            'usuario' => $actor['nome'],
            'papel' => $actor['papel'],
            'ip' => $ip,
            'dispositivo' => Str::substr($device, 0, 190),
            'acao' => Str::substr($input['acao'], 0, 190),
            'detalhes' => Str::substr($input['detalhes'], 0, 1000),
            'status' => $input['status'] ?? 'SUCCESS',
        ])->toArray();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public function listClientEvents(int $skip, int $take): array
    {
        $total = EventoCliente::query()->count();
        $items = EventoCliente::query()->orderBy('criado_em', 'desc')->skip($skip)->take($take)->get()->map->toArray()->all();

        return ['items' => $items, 'total' => $total];
    }
}
