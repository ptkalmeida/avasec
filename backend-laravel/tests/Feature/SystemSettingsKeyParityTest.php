<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SettingsService;
use Tests\TestCase;

/**
 * SettingsService::ALLOWED_KEYS é réplica MANUAL do tipo `systemSettings` em
 * src/context/LMSContext.tsx — mesmo pacto de config/features.php ↔ features.ts.
 *
 * Divergir tem consequência assimétrica e por isso o teste existe: chave nova no
 * frontend e ausente na lista faz a configuração ser recusada com 400 no PUT (a
 * tela do admin para de salvar); e a lista é a fronteira do que fica PÚBLICO,
 * porque o GET desta rota não exige autenticação.
 */
final class SystemSettingsKeyParityTest extends TestCase
{
    public function test_allowed_keys_mirror_the_frontend_type(): void
    {
        $tsPath = dirname(base_path()).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR
            .'context'.DIRECTORY_SEPARATOR.'LMSContext.tsx';
        $this->assertFileExists($tsPath, 'src/context/LMSContext.tsx não encontrado — a fonte mudou de lugar?');

        $source = (string) file_get_contents($tsPath);

        // Bloco `systemSettings: { ... };` da interface do contexto.
        $achou = preg_match('/\n\s*systemSettings:\s*\{(.*?)\n\s*\};/s', $source, $bloco);
        $this->assertSame(1, $achou, 'Bloco systemSettings não encontrado em LMSContext.tsx — o formato mudou?');

        preg_match_all('/^\s*(\w+)\??:\s*[\w\[\]|\' ]+;\s*$/m', $bloco[1], $matches, PREG_SET_ORDER);
        $tsKeys = array_map(static fn (array $m): string => $m[1], $matches);
        $this->assertNotEmpty($tsKeys, 'Nenhuma chave lida do bloco systemSettings — o formato mudou?');

        $phpKeys = SettingsService::ALLOWED_KEYS;
        sort($tsKeys);
        sort($phpKeys);

        $this->assertSame(
            $tsKeys,
            $phpKeys,
            'SettingsService::ALLOWED_KEYS divergiu de systemSettings em LMSContext.tsx. '
            .'Lembre-se: tudo nesta lista é público (GET /api/system-settings não exige login).',
        );
    }
}
