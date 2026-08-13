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
    /** @param array{name:string,role:string}|null $actorOverride */
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
                'user' => is_string($actor['name'] ?? null) ? $actor['name'] : 'Visitante Anônimo',
                'role' => is_string($actor['role'] ?? null) ? $actor['role'] : 'anonymous',
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

    /** @return array{name:string,role:string} */
    private function actorFromRequest(Request $request): array
    {
        $authUser = $request->attributes->get('auth_user');
        if (is_array($authUser)) {
            return [
                'name' => is_string($authUser['name'] ?? null) ? $authUser['name'] : 'Visitante Anônimo',
                'role' => is_string($authUser['role'] ?? null) ? $authUser['role'] : 'anonymous',
            ];
        }

        return ['name' => 'Visitante Anônimo', 'role' => 'anonymous'];
    }

    private function clientIp(Request $request): string
    {
        // $request->ip() já resolve o X-Forwarded-For, mas SÓ quando vem de um proxy
        // confiável (TrustProxies em bootstrap/app.php). Ler o header cru permitiria a
        // qualquer cliente forjar o IP registrado na auditoria (X-Forwarded-For: 8.8.8.8).
        return $request->ip() ?? 'desconhecido';
    }
}
