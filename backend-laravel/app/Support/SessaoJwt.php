<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\JwtAuthenticate;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * O cookie de sessão, num lugar só.
 *
 * Ele nasceu dentro do `AuthController` quando a API era o único jeito de
 * entrar. Com o login em Blade (fase M2), passaram a existir **dois** lugares
 * que emitem sessão, e cookie de sessão é precisamente o tipo de coisa que não
 * pode ser escrita duas vezes: bastaria um `secure` ou um `sameSite` diferente
 * entre as duas para o navegador tratá-los como cookies distintos — e a pessoa
 * entrar por uma porta e continuar deslogada na outra.
 *
 * Os valores espelham o `SESSION_COOKIE_OPTIONS` do Node, que é o que o
 * frontend sempre recebeu: HttpOnly, SameSite=Lax, path `/`, 12 h, `secure` só
 * em produção (em desenvolvimento o endereço é http e um cookie `secure` seria
 * descartado em silêncio).
 */
final class SessaoJwt
{
    public static function cookie(string $token): Cookie
    {
        return cookie(
            name: JwtAuthenticate::SESSION_COOKIE,
            value: $token,
            minutes: Jwt::TTL_SECONDS / 60,
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    /** O cookie que encerra a sessão. Mesmo nome e mesmo caminho, ou não apaga. */
    public static function esquecer(): Cookie
    {
        return cookie()->forget(JwtAuthenticate::SESSION_COOKIE, '/');
    }
}
