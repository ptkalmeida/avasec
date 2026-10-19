<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AreaDoAluno;
use App\Http\Middleware\FeatureGate;
use App\Http\Middleware\IdentidadeOpcional;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\OptionalJwt;
use App\Http\Middleware\RequireActiveAccount;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Saúde e status da Norma TI-SECEC (C.8), sem o grupo "web": ver routes/saude.php.
        then: function (): void {
            Route::group([], base_path('routes/saude.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confia apenas no proxy reverso local (Nginx -> PHP-FPM no mesmo host, ver
        // DEPLOY_LARAVEL.md). Sem isto: (a) $request->ip() retorna sempre o IP do proxy,
        // colapsando todos os rate limiters (por IP) num balde único — um atacante trava
        // o login de todos; (b) o X-Forwarded-For do cliente seria aceito, falsificando
        // o IP da auditoria. Com TrustProxies, o XFF só é honrado vindo destes IPs.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);

        $middleware->alias([
            'feature' => FeatureGate::class,
            // Versão web do `jwt`: mesma leitura do mesmo cookie, mas quem não
            // passa é levado ao login em vez de receber JSON de erro.
            'aluno.web' => AreaDoAluno::class,
            'jwt' => JwtAuthenticate::class,
            'jwt.optional' => OptionalJwt::class,
            'active' => RequireActiveAccount::class,
            'role' => RequireRole::class,
        ]);

        // O cookie de sessão é emitido pelo Node (JWT), não pelo Laravel — não deve
        // passar pela criptografia de cookies do Laravel, senão a leitura falharia.
        $middleware->encryptCookies(except: ['ava_session']);

        // Cabeçalhos de segurança em toda resposta da API (equivalente ao helmet do Node).
        $middleware->api(append: [SecurityHeaders::class]);

        /*
         * Toda tela pública passa a saber se há alguém logado — sem barrar
         * ninguém. É o que permite ao cabeçalho do portal oferecer "Meu painel"
         * a quem já entrou, como o cabeçalho do React fazia. Sem isto, a pessoa
         * logada veria "Entrar" na home e acharia que a sessão caiu.
         */
        $middleware->web(append: [IdentidadeOpcional::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // 401/403 são o controle de acesso FUNCIONANDO (visitante deslogado numa rota
        // protegida, perfil sem permissão), não falha de sistema. O registro padrão os
        // gravava como ERROR com stack trace completo — ~45 linhas cada —, e o
        // laravel.log crescia ~100 MB/dia (ver .ai/planejamento/08). Aqui viram
        // WARNING, sem trace, com o caminho da requisição: o volume continua visível
        // (tentativa de acesso indevido, cliente em laço) sem soterrar erro de verdade.
        // `return false` interrompe só o registro padrão; a resposta HTTP é montada
        // pelo render() abaixo e não muda. Qualquer outra exceção segue como ERROR.
        $exceptions->report(function (ApiException $e): ?bool {
            if ($e->status !== 401 && $e->status !== 403) {
                return null;
            }

            $request = request();
            Log::warning($e->getMessage(), [
                'status' => $e->status,
                'code' => $e->errorCode,
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return false;
        });

        // Todas as respostas de erro sob /api/* seguem o contrato do Node:
        // { error: true, code, message } — nunca stack trace.
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => true,
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                ], $e->status);
            }

            return null;
        });

        /*
         * A MESMA exceção, numa tela: vira a página de erro do status que ela
         * já carrega, e não 500.
         *
         * Os services são compartilhados entre a API e as telas em Blade (fase
         * M3), e eles sinalizam "não encontrado" lançando `ApiException`. Sem
         * esta linha, `/aluno/curso/nao-existe` respondia **500** — o servidor
         * dizendo "eu quebrei" onde o certo é "isso não existe". Além de mentir
         * para quem vê, enchia o log de erro com o que é navegação normal.
         *
         * Só para fora de `/api/*`: lá o contrato JSON acima continua mandando.
         */
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            abort($e->status, $e->getMessage());
        });

        // Erros de validação nativos do Laravel também no formato do Node (400 VALIDATION_ERROR).
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => true,
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->validator->errors()->first(),
                ], 400);
            }

            return null;
        });
    })->create();
