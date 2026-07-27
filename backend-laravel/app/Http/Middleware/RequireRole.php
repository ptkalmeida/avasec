<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivalente ao requireRole do Node (src/server/middlewares/rbac.ts). Roda sempre
 * DEPOIS do JwtAuthenticate. Uso: ->middleware('role:instructor,admin')
 */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->attributes->get('auth_user');
        $role = is_array($user) ? ($user['role'] ?? null) : null;

        if (! is_string($role) || ! in_array($role, $roles, true)) {
            throw ApiException::forbidden();
        }

        return $next($request);
    }
}
