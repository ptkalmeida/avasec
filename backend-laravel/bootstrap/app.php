<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\FeatureGate;
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
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
