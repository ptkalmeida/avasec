<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Trava o risco registrado em .ai/planejamento/02-arquitetura-tecnica.md:
 * config/features.php é uma réplica MANUAL de src/config/features.ts, e uma
 * divergência muda o comportamento visível da API (flag desligada => 404).
 * Este teste falha se os dois arquivos saírem de sincronia — em chaves OU valores.
 */
final class FeatureFlagParityTest extends TestCase
{
    public function test_features_php_mirrors_features_ts(): void
    {
        $tsPath = dirname(base_path()).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'features.ts';
        $this->assertFileExists($tsPath, 'src/config/features.ts não encontrado — a fonte das flags mudou de lugar?');

        $source = (string) file_get_contents($tsPath);
        preg_match_all('/^\s*(\w+):\s*(true|false)\s*,?\s*$/m', $source, $matches, PREG_SET_ORDER);

        $tsFlags = [];
        foreach ($matches as $match) {
            $tsFlags[$match[1]] = $match[2] === 'true';
        }
        $this->assertNotEmpty($tsFlags, 'Nenhuma flag encontrada em features.ts — o formato do arquivo mudou?');

        $phpFlags = config('features');
        $this->assertIsArray($phpFlags);

        ksort($tsFlags);
        ksort($phpFlags);
        $this->assertSame(
            $tsFlags,
            $phpFlags,
            'config/features.php divergiu de src/config/features.ts — sincronize os dois arquivos.',
        );
    }
}
