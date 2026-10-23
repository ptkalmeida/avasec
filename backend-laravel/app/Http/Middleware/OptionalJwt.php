<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espelha attachUserIfPresent do Node: se houver um token válido, anexa a identidade;
 * se não houver token, segue como anônimo (não falha). Usado no /register para detectar
 * provisionamento por admin e nas listagens públicas que mostram mais a quem tem sessão.
 * Um token PRESENTE mas inválido segue como anônimo.
 *
 * O PAPEL vem do banco, não do token (achado da varredura de segurança de 23/10/2026):
 * o token vale 12 h, e quem teve a conta bloqueada, inativada ou rebaixada nesse
 * intervalo continuava, por esta rota, provisionando administradores e lendo o catálogo
 * como gestor. Conta que não existe, está inativada, bloqueada ou pendente vale como
 * visitante — sem 401, porque a rota é pública.
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
                    $vigente = $this->conferirNoBanco($payload);
                    if ($vigente !== null) {
                        $request->attributes->set('auth_user', $vigente);
                    }
                }
            } catch (ApiException) {
                // Token presente mas inválido: segue como anônimo (não é 401 aqui),
                // igual ao attachUserIfPresent do Node.
            }
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null a identidade com o papel ATUAL, ou null se não vale mais
     */
    private function conferirNoBanco(array $payload): ?array
    {
        $sub = $payload['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            return null;
        }

        $user = User::query()->withTrashed()->find($sub, ['status', 'role', 'inativadoEm']);
        if ($user === null || $user->estaInativo() || $user->status !== 'active' || ! is_string($user->role)) {
            return null;
        }

        $payload['role'] = $user->role;

        return $payload;
    }
}
