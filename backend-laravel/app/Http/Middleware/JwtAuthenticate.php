<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Support\Jwt;
use Closure;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Espelha requireAuth do Node: exige um JWT válido e anexa a identidade
 * (sub/name/role) à request. NÃO confere status da conta — isso é feito
 * separadamente por RequireActiveAccount, exatamente como no Node
 * (requireAuth vs requireActiveAccount).
 *
 * Token vem do cookie HttpOnly `ava_session` (preferido) ou do header
 * Authorization: Bearer (clientes de API/testes).
 */
final class JwtAuthenticate
{
    public const SESSION_COOKIE = 'ava_session';

    public function handle(Request $request, Closure $next): Response
    {
        $payload = self::decode(self::extractToken($request));
        if ($payload === null) {
            throw ApiException::unauthorized('Token de autenticação ausente.');
        }

        $request->attributes->set('auth_user', $payload);

        return $next($request);
    }

    /** @return array{sub:string,name:string,role:string}|null */
    public static function decode(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $decoded = FirebaseJwt::decode($token, new Key(Jwt::secret(), 'HS256'));
        } catch (Throwable) {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }

        $sub = $decoded->sub ?? null;
        if (! is_string($sub) || $sub === '') {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }

        return ['sub' => $sub, 'name' => $decoded->name ?? null, 'role' => $decoded->role ?? null];
    }

    public static function extractToken(Request $request): ?string
    {
        $cookie = $request->cookie(self::SESSION_COOKIE);
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }

        $header = $request->header('Authorization');
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
