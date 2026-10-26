<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Support\Jwt;
use App\Support\Sessoes;
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

        // Sessão cancelada no servidor (troca de senha, bloqueio, "Sair"): ADR 022.
        // Token sem `sv`/`jti` (emitido antes dela) vale como versão 0 e não tem `jti`.
        Sessoes::conferir(
            $sub,
            is_numeric($decoded->sv ?? null) ? (int) $decoded->sv : 0,
            is_string($decoded->jti ?? null) && $decoded->jti !== '' ? $decoded->jti : null,
        );

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
