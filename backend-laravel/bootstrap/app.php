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
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
