<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Paridade obrigatória com tests/frontend/safeUrl.test.ts — os mesmos casos, nos dois
 * lados. Se um aceitar o que o outro recusa, existe um caminho em que o dado entra
 * pela API e chega ao href do aluno.
 */
final class SafeUrlTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function urlsPerigosas(): array
    {
        $casos = [
            'javascript:alert(1)',
            'JavaScript:alert(1)',
            'JAVASCRIPT:alert(document.cookie)',
            '  javascript:alert(1)',
            "java\tscript:alert(1)",
            "java\nscript:alert(1)",
            "java\rscript:alert(1)",
            'javascript :alert(1)',
            'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'vbscript:msgbox(1)',
            'file:///etc/passwd',
            'blob:https://exemplo.com/uuid',
            'about:blank',
        ];

        return array_combine(
            array_map(static fn (string $u): string => addcslashes($u, "\t\n\r"), $casos),
            array_map(static fn (string $u): array => [$u], $casos),
        );
    }

    /** @return array<string, array{string}> */
    public static function urlsValidas(): array
    {
        $casos = [
            'https://meet.google.com/abc-defg-hij',
            'http://exemplo.com/material.pdf',
            'https://exemplo.com/caminho?a=1&b=2#frag',
            '/uploads/documento.pdf',
            '/uploads/subpasta/arquivo.docx',
        ];

        return array_combine($casos, array_map(static fn (string $u): array => [$u], $casos));
    }

    #[DataProvider('urlsPerigosas')]
    public function test_recusa_url_perigosa(string $url): void
    {
        $this->assertFalse(SafeUrl::isValid($url), "Deveria recusar: {$url}");
        $this->assertNull(SafeUrl::sanitize($url));
    }

    #[DataProvider('urlsValidas')]
    public function test_aceita_url_valida(string $url): void
    {
        $this->assertTrue(SafeUrl::isValid($url), "Deveria aceitar: {$url}");
        $this->assertSame($url, SafeUrl::sanitize($url));
    }

    public function test_recusa_vazio_e_nulo(): void
    {
        $this->assertFalse(SafeUrl::isValid(''));
        $this->assertFalse(SafeUrl::isValid('   '));
        $this->assertFalse(SafeUrl::isValid(null));
    }

    public function test_recusa_travessia_de_diretorio_em_caminho_relativo(): void
    {
        $this->assertFalse(SafeUrl::isValid('/uploads/../../etc/passwd'));
    }

    public function test_recusa_url_sem_esquema(): void
    {
        // Num href isso viraria caminho relativo silencioso.
        $this->assertFalse(SafeUrl::isValid('exemplo.com/material.pdf'));
    }

    public function test_recusa_http_sem_host(): void
    {
        $this->assertFalse(SafeUrl::isValid('https://'));
        $this->assertFalse(SafeUrl::isValid('http:///caminho'));
    }

    public function test_recusa_url_acima_do_limite(): void
    {
        $this->assertFalse(SafeUrl::isValid('https://exemplo.com/'.str_repeat('a', 2000)));
    }

    public function test_devolve_a_url_original_nao_a_normalizada(): void
    {
        // A normalização existe só para decidir; o valor entregue é o que o autor gravou.
        $this->assertSame('https://exemplo.com/a b', SafeUrl::sanitize('  https://exemplo.com/a b '));
    }
}
