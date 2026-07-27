<?php

declare(strict_types=1);

namespace App\Support;

use Firebase\JWT\JWT as FirebaseJwt;

/**
 * Emissão de JWT compatível com o backend Node (src/server/middlewares/auth.ts:signToken):
 * HS256, mesmo segredo compartilhado, payload { sub, name, role } e expiração de 12h.
 * Tokens emitidos aqui são verificáveis pelo Node e vice-versa durante a migração.
 */
final class Jwt
{
    /** Duração do token em segundos (12h) — igual ao expiresIn '12h' do Node. */
    public const TTL_SECONDS = 12 * 60 * 60;

    public static function issue(string $sub, string $name, string $role): string
    {
        $now = time();

        return FirebaseJwt::encode(
            [
                'sub' => $sub,
                'name' => $name,
                'role' => $role,
                'iat' => $now,
                'exp' => $now + self::TTL_SECONDS,
            ],
            self::secret(),
            'HS256',
        );
    }

    public static function secret(): string
    {
        $secret = config('app.jwt_secret');

        return is_string($secret) ? $secret : '';
    }
}
