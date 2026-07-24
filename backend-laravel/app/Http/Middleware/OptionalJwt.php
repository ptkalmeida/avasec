<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espelha attachUserIfPresent do Node: se houver um token válido, anexa a identidade;
 * se não houver token, segue como anônimo (não falha). Usado no /register para detectar
 * provisionamento por admin. Um token PRESENTE mas inválido ainda gera 401 (mesma
 * semântica de decode do JwtAuthenticate).
 */
final class OptionalJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = JwtAuthenticate::extractToken($request);
        if ($token !== null) {
            try {
                $payload = JwtAuthenticate::decode($token);
                if ($payload !== null) {
                    $request->attributes->set('auth_user', $payload);
                }
            } catch (ApiException) {
                // Token presente mas inválido: segue como anônimo (não é 401 aqui),
                // igual ao attachUserIfPresent do Node.
            }
        }

        return $next($request);
    }
}
