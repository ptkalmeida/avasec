<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Auditoria server-side — espelha src/server/services/auditService.ts. Ações sensíveis
 * (login, falha de login, cadastro, troca de senha, mudança de status, exclusão) são
 * gravadas AQUI, no servidor, nunca via POST do cliente. Nunca registra senha/token.
 * Uma falha de auditoria não pode derrubar a request principal.
 */
final class AuditLogger
{
    /** @param array{name:mixed,role:mixed}|null $actorOverride */
    public function log(
        Request $request,
        string $action,
        string $details,
        string $status = 'SUCCESS',
        ?array $actorOverride = null,
    ): void {
        $actor = $actorOverride ?? $this->actorFromRequest($request);
        $now = CarbonImmutable::now();

        try {
            DB::table('SecurityLog')->insert([
                'id' => 'log-'.$now->getTimestampMs().'-'.Str::lower(Str::random(6)),
                'timestamp' => $now->format('H:i:s').' '.$now->format('d/m/Y'),
                'user' => (string) ($actor['name'] ?? 'Visitante Anônimo'),
                'role' => (string) ($actor['role'] ?? 'anonymous'),
                'ipAddress' => $this->clientIp($request),
                'device' => Str::substr((string) $request->userAgent(), 0, 200) ?: 'desconhecido',
                'action' => $action,
                'details' => Str::substr($details, 0, 1000),
                'status' => $status,
            ]);
        } catch (Throwable $e) {
            // Auditoria não derruba a request principal, mas o erro fica visível no log do servidor.
            report($e);
        }
    }

    /** @return array{name:mixed,role:mixed} */
    private function actorFromRequest(Request $request): array
    {
        $authUser = $request->attributes->get('auth_user');
        if (is_array($authUser)) {
            return ['name' => $authUser['name'] ?? 'Visitante Anônimo', 'role' => $authUser['role'] ?? 'anonymous'];
        }

        return ['name' => 'Visitante Anônimo', 'role' => 'anonymous'];
    }

    private function clientIp(Request $request): string
    {
        $forwarded = $request->header('X-Forwarded-For');
        if (is_string($forwarded) && $forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return $request->ip() ?? 'desconhecido';
    }
}
