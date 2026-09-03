<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espelha requireActiveAccount do Node: reconfere o status da conta NO BANCO a cada
 * request (nunca confia no status embutido no token, que pode estar defasado).
 * Roda sempre depois do JwtAuthenticate.
 *  - conta inexistente -> 401
 *  - blocked            -> 403 ACCOUNT_BLOCKED
 *  - pending            -> 403 ACCOUNT_PENDING_CONFIRMATION
 *
 * Também reconfere o ROLE no banco e sobrescreve o valor vindo do token: um usuário
 * rebaixado (ex.: admin -> student) não pode manter privilégios com um token antigo
 * válido por até 12h. O RequireRole, que roda depois, passa a decidir pelo role real.
 */
final class RequireActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->attributes->get('auth_user');
        $sub = is_array($authUser) ? ($authUser['sub'] ?? null) : null;

        /*
         * withTrashed de propósito: com a inativação da ADR 12, a conta
         * inativada deixa de aparecer nas consultas padrão. Sem isto ela cairia
         * no ramo "conta inexistente" e o cliente receberia 401 "token inválido
         * ou expirado" — mensagem enganosa para quem teve o acesso encerrado, e
         * que manda o app tentar renovar sessão em vez de dizer o que houve.
         */
        $user = is_string($sub)
            ? User::query()->withTrashed()->find($sub, ['status', 'role', 'inativadoEm'])
            : null;
        if ($user === null) {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }

        // Conta inativada não entra: preservar o registro para auditoria não
        // pode virar porta aberta.
        if ($user->estaInativo()) {
            throw ApiException::accountBlocked();
        }

        if ($user->status === 'blocked') {
            throw ApiException::accountBlocked();
        }
        if ($user->status === 'pending_confirmation') {
            throw ApiException::accountPending();
        }

        if (is_array($authUser) && is_string($user->role)) {
            $authUser['role'] = $user->role;
            $request->attributes->set('auth_user', $authUser);
        }

        return $next($request);
    }
}
