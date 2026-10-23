<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança nas TELAS (Blade) servidas pelo Laravel.
 *
 * Desde a fase M7 (ADR 020) o Laravel é a única aplicação, mas só a API recebia
 * `SecurityHeaders`; as telas dependiam do Nginx para tê-los, e quem desenvolve ou
 * homologa sem Nginx na frente ficava sem nada. Aqui vai o mínimo que não depende
 * do ambiente e não muda o comportamento de nenhuma tela:
 *
 *  - `nosniff`: o navegador não "adivinha" o tipo de um arquivo servido pela tela;
 *  - `SAMEORIGIN`: a tela só pode ser embutida em página do próprio sistema, contra
 *    clickjacking (a CSP completa, com `frame-ancestors`, é do Nginx).
 *
 * O `Referrer-Policy` fica de fora de propósito: o player do YouTube precisa do
 * referenciador para abrir o vídeo, e a política é decisão do Nginx.
 */
final class CabecalhosDaPaginaWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
