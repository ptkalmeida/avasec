<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * O roteador da migração para MPA (ADR 017, plano 16, fase M0).
 *
 * Três promessas, e a terceira é a que de fato justifica este arquivo:
 *
 * 1. Rota registrada em `routes/web.php` é atendida pelo Laravel.
 * 2. Rota não registrada cai no SPA React — e `/api/*` continua devolvendo o
 *    JSON de erro do contrato, nunca o HTML do React.
 * 3. **`rotas-laravel.json` cobre toda rota do Laravel.** É o arquivo que o
 *    `vite.config.ts` lê para montar o proxy de desenvolvimento. Esquecer de
 *    acrescentar um prefixo ali produz o sintoma mais confuso que esta migração
 *    pode dar: a tela funciona em produção e não funciona na máquina de quem
 *    está desenvolvendo. Este teste é o que impede isso de passar.
 */
final class RoteadorMpaTest extends TestCase
{
    /**
     * Caminhos que o Laravel atende sem precisar estar no JSON.
     *
     * O fallback não é um caminho — é o "todo o resto". E `/` é a raiz, que o
     * Vite já serve em desenvolvimento por ser a própria página do React.
     */
    private const FORA_DA_REGRA = ['/', '{fallbackPlaceholder}'];

    public function test_rota_registrada_e_atendida_em_blade_e_nao_pelo_react(): void
    {
        $resposta = $this->get('/sistema/fundacao-mpa');

        $resposta->assertOk();
        $resposta->assertSee('Esta página foi renderizada pelo Laravel, em Blade.', false);
        // O casco do React tem a âncora onde ele se monta; a página Blade, não.
        $resposta->assertDontSee('<div id="root">', false);
    }

    public function test_rota_nao_migrada_cai_no_spa_react(): void
    {
        $resposta = $this->get('/uma-tela-que-ainda-nao-migrou');

        // Sem `dist/` montado, a resposta honesta é 503 e não 404: a rota existe,
        // o que falta é o build. Com build, vem o casco do React.
        if ($resposta->getStatusCode() === 503) {
            $this->markTestSkipped('Sem `dist/` — rode `npm run build` para exercitar este caminho.');
        }

        $resposta->assertOk();
        $resposta->assertSee('<div id="root">', false);
    }

    /**
     * O fallback é só do lado web. Um caminho inexistente sob `/api/` tem de
     * continuar devolvendo erro de API — se devolvesse o HTML do React com 200,
     * todo cliente da API passaria a receber uma página em vez de um erro.
     */
    public function test_caminho_inexistente_na_api_nao_recebe_o_html_do_react(): void
    {
        $resposta = $this->getJson('/api/rota-que-nao-existe');

        $resposta->assertNotFound();
        $resposta->assertDontSee('<div id="root">', false);
    }

    /**
     * Caminho de recurso do SERVIDOR que não casou com rota nenhuma é 404.
     *
     * Esta regra nasceu de uma regressão real: com o fallback engolindo tudo,
     * `/uploads/../../.env` — que a rota recusa de propósito — passou a responder
     * 200 com a página do React. A recusa continuava acontecendo, mas deixava de
     * ser visível para quem chama, e isso é pior que o erro original, porque uma
     * varredura de segurança leria 200 como sucesso.
     *
     * Pega pelo `UploadTest::test_static_upload_route_rejects_traversal_and_missing_file`,
     * que já existia. Este caso cobre os outros prefixos antes que alguém descubra
     * um por um.
     */
    public function test_recurso_do_servidor_inexistente_nao_cai_no_spa(): void
    {
        $caminhos = [
            '/uploads/'.rawurlencode('../../.env'),
            '/uploads/arquivo-que-nao-existe.png',
            '/health/inexistente',
            '/sistema/inexistente',
        ];

        foreach ($caminhos as $caminho) {
            $resposta = $this->get($caminho);

            $this->assertSame(
                404,
                $resposta->getStatusCode(),
                "{$caminho} devia ser 404; se virar 200, o SPA está escondendo a recusa do servidor.",
            );
            $resposta->assertDontSee('<div id="root">', false);
        }
    }

    public function test_rotas_laravel_json_cobre_toda_rota_registrada(): void
    {
        $arquivo = base_path('../rotas-laravel.json');
        $this->assertFileExists($arquivo, 'O contrato de rotas sumiu; o proxy do Vite depende dele.');

        /** @var array{prefixos: list<string>} $contrato */
        $contrato = json_decode((string) file_get_contents($arquivo), true, 512, JSON_THROW_ON_ERROR);
        $prefixos = $contrato['prefixos'];

        $descobertas = [];

        foreach (Route::getRoutes() as $rota) {
            $caminho = '/'.ltrim($rota->uri(), '/');

            if (in_array($caminho, self::FORA_DA_REGRA, true) || $rota->isFallback) {
                continue;
            }

            foreach ($prefixos as $prefixo) {
                if ($caminho === $prefixo || Str::startsWith($caminho, rtrim($prefixo, '/').'/')) {
                    continue 2;
                }
            }

            $descobertas[$caminho] = true;
        }

        $this->assertSame(
            [],
            array_keys($descobertas),
            'Rota do Laravel fora de `rotas-laravel.json`. Em desenvolvimento o Vite não vai '
            .'encaminhá-la, e a tela só funcionará em produção. Acrescente o prefixo ao JSON: '
            .implode(', ', array_keys($descobertas)),
        );
    }

    /**
     * O caminho inverso: prefixo declarado que não corresponde a rota nenhuma.
     *
     * Não é erro — o `/up` do Laravel e prefixos preparados para a fase seguinte
     * são legítimos. Mas o proxy encaminharia um caminho que o Laravel responde
     * com 404, então vale saber. Falha só se o JSON estiver malformado.
     */
    public function test_contrato_de_rotas_e_json_valido_e_sem_prefixo_repetido(): void
    {
        /** @var array{prefixos: list<string>} $contrato */
        $contrato = json_decode(
            (string) file_get_contents(base_path('../rotas-laravel.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $prefixos = $contrato['prefixos'];

        $this->assertNotEmpty($prefixos);
        $this->assertSame(array_values(array_unique($prefixos)), $prefixos, 'Prefixo repetido no contrato.');

        foreach ($prefixos as $prefixo) {
            $this->assertStringStartsWith('/', $prefixo, "Prefixo sem barra inicial: {$prefixo}");
            $this->assertSame(rtrim($prefixo, '/'), $prefixo, "Prefixo com barra final: {$prefixo}");
        }
    }
}
