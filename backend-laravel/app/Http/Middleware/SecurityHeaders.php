<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança nas respostas da API — o backend Node usava helmet; aqui
 * aplicamos o subconjunto relevante para respostas JSON/download (a CSP completa do
 * frontend é responsabilidade do Nginx que serve o SPA — ver DEPLOY_LARAVEL.md):
 *  - nosniff: impede o navegador de "adivinhar" o tipo de um download (relevante
 *    para /api/files, que entrega arquivos enviados por terceiros);
 *  - DENY: respostas da API nunca são embutíveis em iframes;
 *  - Referrer-Policy: não vaza URLs internas para origens externas.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
