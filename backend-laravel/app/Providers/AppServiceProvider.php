<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\ApiException;
use App\Support\Jwt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->assertStrongJwtSecret();
        $this->configureRateLimiters();
    }

    /**
     * Fail-fast em produção: um JWT_SECRET curto ou com valor de exemplo permite
     * a qualquer um forjar tokens (inclusive de admin, já que o role vem do token).
     * Reintroduz o guard que existia no backend Node (env.ts) e que se perdeu na
     * migração — o invariante "JWT_SECRET ≥ 32 chars" (CLAUDE.md) passa a ser código.
     */
    private function assertStrongJwtSecret(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $secret = Jwt::secret();
        $isPlaceholder = $secret === '' || str_contains(mb_strtolower($secret), 'troque');
        if (mb_strlen($secret) < 32 || $isPlaceholder) {
            throw new RuntimeException(
                'JWT_SECRET inseguro: defina um segredo aleatório com ao menos 32 caracteres em produção (o valor de exemplo não é aceito).'
            );
        }
    }

    /**
     * Rate limiters nomeados das rotas de auth — mesmos limites/janelas do Node
     * (src/server/middlewares/rateLimiters.ts), keyed por IP, com 429 no formato
     * padronizado { error, code: RATE_LIMITED, message }.
     */
    private function configureRateLimiters(): void
    {
        $tooMany = static fn () => throw ApiException::tooManyRequests();

        // login: 10 por 15 min
        RateLimiter::for('auth-login', static fn (Request $r) => Limit::perMinutes(15, 10)->by((string) $r->ip())->response($tooMany));
        // registro: 10 por 60 min
        RateLimiter::for('auth-register', static fn (Request $r) => Limit::perMinutes(60, 10)->by((string) $r->ip())->response($tooMany));
        // troca de senha: 5 por 15 min
        RateLimiter::for('auth-password', static fn (Request $r) => Limit::perMinutes(15, 5)->by((string) $r->ip())->response($tooMany));
        // matrícula/admissão (self-enroll, criar admissão): 20 por 15 min
        RateLimiter::for('enrollment', static fn (Request $r) => Limit::perMinutes(15, 20)->by((string) $r->ip())->response($tooMany));
        // verificação pública de certificado: 30 por 15 min (anti-scraping)
        RateLimiter::for('cert-lookup', static fn (Request $r) => Limit::perMinutes(15, 30)->by((string) $r->ip())->response($tooMany));
        // upload de arquivos: 30 por 15 min
        RateLimiter::for('upload', static fn (Request $r) => Limit::perMinutes(15, 30)->by((string) $r->ip())->response($tooMany));
        // solicitação acadêmica (justificativa): 20 por 60 min
        RateLimiter::for('justification', static fn (Request $r) => Limit::perMinutes(60, 20)->by((string) $r->ip())->response($tooMany));
        // exportação de dados gerenciais: 10 por 60 min
        RateLimiter::for('export', static fn (Request $r) => Limit::perMinutes(60, 10)->by((string) $r->ip())->response($tooMany));
    }
}
