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
 */
final class RequireActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->attributes->get('auth_user');
        $sub = $authUser['sub'] ?? null;

        $user = is_string($sub) ? User::query()->find($sub, ['status']) : null;
        if ($user === null) {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }

        if ($user->status === 'blocked') {
            throw ApiException::accountBlocked();
        }
        if ($user->status === 'pending_confirmation') {
            throw ApiException::accountPending();
        }

        return $next($request);
    }
}
