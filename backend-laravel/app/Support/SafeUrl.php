<?php

declare(strict_types=1);

namespace App\Support;

/**
 * URL que pode ser entregue ao navegador dentro de um atributo href/src.
 *
 * Motivo: campos como thumbnail, coverImage, documents[].url e meetingLink eram
 * validados apenas como `string, max:2000`. Um instrutor podia gravar
 * `javascript:fetch('/api/...')` e o valor ia direto para um `href` na tela do aluno;
 * o cookie de sessão é HttpOnly, mas viaja automaticamente, então o script agiria
 * como a vítima. Este é o `VideoUrlRule` do resto das URLs — mesmo desenho, mesmo
 * lugar, para a regra ser encontrável (espelhado em src/utils/safeUrl.ts).
 *
 * Aceita: http, https e caminho relativo começando em / (uploads do próprio sistema).
 * Recusa: qualquer outro esquema — javascript:, data:, vbscript:, file:, blob: — e
 * URL sem host, além de travessia de diretório em caminho relativo.
 */
final class SafeUrl
{
    private const MAX_LENGTH = 2000;

    public static function isValid(?string $url): bool
    {
        return self::sanitize($url) !== null;
    }

    /**
     * Devolve a URL se for segura para href/src, ou null.
     *
     * A comparação é feita sobre o valor com espaços em branco removidos: navegador
     * ignora tab/newline/CR dentro do esquema, então "java\tscript:alert(1)" executa.
     * Validar a string crua deixaria passar exatamente esse caso.
     */
    public static function sanitize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '' || mb_strlen($url) > self::MAX_LENGTH) {
            return null;
        }

        // Remove TODO espaço em branco (incluindo \0, que também é ignorado por
        // alguns parsers) antes de olhar o esquema.
        $normalizado = (string) preg_replace('/[\s\x00]+/u', '', $url);
        if ($normalizado === '') {
            return null;
        }

        // Caminho relativo do próprio sistema: nunca carrega esquema.
        if (str_starts_with($normalizado, '/')) {
            return str_contains($normalizado, '..') ? null : $url;
        }

        // Sem esquema explícito e sem "/" inicial não é URL utilizável num href:
        // "javascript:..." tem esquema; "exemplo.com/x" viraria caminho relativo
        // silencioso. Exigir esquema evita interpretação ambígua.
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $normalizado, $m) !== 1) {
            return null;
        }

        $esquema = strtolower($m[1]);
        if ($esquema !== 'http' && $esquema !== 'https') {
            return null;
        }

        $host = parse_url($normalizado, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return $url;
    }
}
