<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\ApiException;
use App\Http\Middleware\JwtAuthenticate;
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
     * Rate limiters nomeados das rotas sensíveis.
     *
     * CHAVE: nunca só o IP. O AVASEC é usado em laboratório de escola e em rede
     * doméstica atrás de CGNAT — dezenas de alunos legítimos saem pelo MESMO IP
     * público. Um limitador keyed só por IP transforma o início de uma aula em
     * bloqueio: com 10 logins por IP, o 11º aluno do laboratório não entra.
     *
     * Por isso:
     *  - rota autenticada  -> chave = id do usuário (o token já identifica quem é);
     *  - rota de login     -> chave = identificador enviado + IP, com um teto por IP
     *                         bem mais alto que segura força bruta distribuída.
     *
     * A identidade é lida do token AQUI, e não do atributo que o middleware de JWT
     * anexa: o Laravel ordena ThrottleRequests por prioridade e o executa ANTES do
     * middleware de autenticação, independentemente da ordem declarada na rota — sem
     * isto todos os baldes autenticados caíam no fallback por IP, exatamente o
     * problema que este arquivo existe para evitar.
     */
    private function configureRateLimiters(): void
    {
        $tooMany = static fn () => throw ApiException::tooManyRequests();

        // login: 10 por 15 min para o par (identificador, IP). Contar por identificador
        // isola cada aluno do laboratório: cada CPF tem o próprio balde. O teto por IP
        // (100/15min) impede que um único IP percorra uma lista de contas.
        RateLimiter::for('auth-login', static fn (Request $r) => [
            Limit::perMinutes(15, 10)->by('login:'.self::loginIdentifier($r).'|'.$r->ip())->response($tooMany),
            Limit::perMinutes(15, 100)->by('login-ip:'.$r->ip())->response($tooMany),
        ]);
        // registro: 10 por 60 min por IP. Aqui o IP é a única chave possível (ainda não
        // existe conta), então o limite é por IP mesmo — é o cadastro, não o acesso diário.
        RateLimiter::for('auth-register', static fn (Request $r) => Limit::perMinutes(60, 10)->by((string) $r->ip())->response($tooMany));
        // troca de senha: 5 por 15 min POR USUÁRIO (rota autenticada).
        RateLimiter::for('auth-password', static fn (Request $r) => Limit::perMinutes(15, 5)->by(self::actorKey($r))->response($tooMany));
        // matrícula/admissão: 20 por 15 min por usuário.
        RateLimiter::for('enrollment', static fn (Request $r) => Limit::perMinutes(15, 20)->by(self::actorKey($r))->response($tooMany));
        // verificação pública de certificado: anti-scraping. Sem login, a chave só pode
        // ser o IP; 120/15min acomoda uma secretaria conferindo vários certificados.
        RateLimiter::for('cert-lookup', static fn (Request $r) => Limit::perMinutes(15, 120)->by((string) $r->ip())->response($tooMany));
        // upload de arquivos: 30 por 15 min por usuário (turma entregando atividade).
        RateLimiter::for('upload', static fn (Request $r) => Limit::perMinutes(15, 30)->by(self::actorKey($r))->response($tooMany));
        // solicitação acadêmica (justificativa): 20 por 60 min por usuário.
        RateLimiter::for('justification', static fn (Request $r) => Limit::perMinutes(60, 20)->by(self::actorKey($r))->response($tooMany));
        // exportação de dados gerenciais: 10 por 60 min por usuário.
        RateLimiter::for('export', static fn (Request $r) => Limit::perMinutes(60, 10)->by(self::actorKey($r))->response($tooMany));
        // telemetria de UI: rota PÚBLICA que grava no banco (ClientEvent). Sem limite,
        // qualquer anônimo inflava a tabela com texto próprio. Por usuário quando há
        // token; por IP quando anônimo.
        RateLimiter::for('telemetry', static fn (Request $r) => Limit::perMinute(60)->by(self::actorKey($r))->response($tooMany));
    }

    /**
     * Chave do balde para rotas autenticadas: id do usuário do token (imune a IP
     * compartilhado). Cai para o IP só quando não há token — rota pública.
     */
    private static function actorKey(Request $r): string
    {
        // Já anexado pelo middleware, quando ele roda antes.
        $user = $r->attributes->get('auth_user');
        if (is_array($user) && is_string($user['sub'] ?? null) && $user['sub'] !== '') {
            return 'user:'.$user['sub'];
        }

        // Caso normal: o throttle roda antes da autenticação, então decodifica o
        // token por conta própria. Token ausente ou inválido cai no IP — quem não se
        // identifica compartilha balde, e é isso que se quer para tráfego anônimo.
        try {
            $payload = JwtAuthenticate::decode(JwtAuthenticate::extractToken($r));
        } catch (ApiException) {
            $payload = null;
        }

        if (is_array($payload) && is_string($payload['sub'] ?? null) && $payload['sub'] !== '') {
            return 'user:'.$payload['sub'];
        }

        return 'ip:'.$r->ip();
    }

    /**
     * Identificador tentado no login (CPF, e-mail ou nome), normalizado para que
     * variações de grafia caiam no mesmo balde. Vazio quando o corpo não traz nenhum.
     */
    private static function loginIdentifier(Request $r): string
    {
        foreach (['cpf', 'email', 'name'] as $field) {
            $value = $r->input($field);
            if (is_string($value) && trim($value) !== '') {
                $normalized = mb_strtolower(trim($value));

                return $field === 'cpf' ? preg_replace('/\D/', '', $normalized) ?? '' : $normalized;
            }
        }

        return '';
    }
}
