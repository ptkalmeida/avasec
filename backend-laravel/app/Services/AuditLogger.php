<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Fuso;
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
    /** @param array{nome:string,papel:string}|null $actorOverride */
    public function log(
        Request $request,
        string $action,
        string $details,
        string $status = 'SUCCESS',
        ?array $actorOverride = null,
    ): void {
        $actor = $actorOverride ?? $this->actorFromRequest($request);
        // Fuso de exibição: o log é lido por pessoa, e em UTC marcava 3h adiante.
        $now = Fuso::agora();

        try {
            DB::table('log_seguranca')->insert([
                'id' => 'log-'.$now->getTimestampMs().'-'.Str::lower(Str::random(6)),
                'registrado_em_texto' => $now->format('H:i:s').' '.$now->format('d/m/Y'),
                // Mesmo instante, em coluna nativa e em UTC (Norma C.2.4). O texto
                // acima continua sendo o que a tela de seguranca exibe; esta coluna
                // e o que permite ordenar. Ordenar pelo texto ordena pela HORA antes
                // da data, e o "mais recente" saia errado.
                'ocorrido_em' => $now->utc()->format('Y-m-d H:i:s'),
                'usuario' => is_string($actor['nome'] ?? null) ? $actor['nome'] : 'Visitante Anônimo',
                'papel' => is_string($actor['papel'] ?? null) ? $actor['papel'] : 'anonymous',
                'ip' => $this->clientIp($request),
                'dispositivo' => Str::substr((string) $request->userAgent(), 0, 200) ?: 'desconhecido',
                'acao' => $action,
                'detalhes' => Str::substr($details, 0, 1000),
                'status' => $status,
            ]);
        } catch (Throwable $e) {
            // Auditoria não derruba a request principal, mas o erro fica visível no log do servidor.
            report($e);
        }
    }

    /** @return array{nome:string,papel:string} */
    private function actorFromRequest(Request $request): array
    {
        $authUser = $request->attributes->get('auth_user');
        if (is_array($authUser)) {
            return [
                'nome' => is_string($authUser['nome'] ?? null) ? $authUser['nome'] : 'Visitante Anônimo',
                'papel' => is_string($authUser['papel'] ?? null) ? $authUser['papel'] : 'anonymous',
            ];
        }

        return ['nome' => 'Visitante Anônimo', 'papel' => 'anonymous'];
    }

    private function clientIp(Request $request): string
    {
        // $request->ip() já resolve o X-Forwarded-For, mas SÓ quando vem de um proxy
        // confiável (TrustProxies em bootstrap/app.php). Ler o header cru permitiria a
        // qualquer cliente forjar o IP registrado na auditoria (X-Forwarded-For: 8.8.8.8).
        return $request->ip() ?? 'desconhecido';
    }
}
