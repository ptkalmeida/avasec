<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `config/menu_publico.php` é réplica MANUAL de `src/config/menuPublico.ts`.
 *
 * Enquanto o cabeçalho existir nas duas tecnologias (fase M2 do plano 16), as
 * duas listas descrevem o mesmo menu para a mesma pessoa na mesma sessão. Uma
 * divergência não quebra teste de unidade nenhum: ela aparece como dois menus
 * diferentes conforme a tela seja Blade ou React — exatamente o tipo de defeito
 * que só é notado quando alguém compara duas telas lado a lado.
 *
 * Mesma ideia do `FeatureFlagParityTest`, e pelo mesmo motivo.
 *
 * Confere RÓTULOS e DESTINOS, não as descrições: o rótulo é o que a pessoa lê
 * para decidir onde clicar, e o destino é onde ela chega. A descrição de item
 * de grupo é texto de apoio, e trava desnecessária quebra entrega por detalhe.
 */
final class MenuPublicoParityTest extends TestCase
{
    public function test_menu_php_espelha_menu_ts(): void
    {
        $tsPath = dirname(base_path()).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'menuPublico.ts';
        $this->assertFileExists($tsPath, 'src/config/menuPublico.ts não encontrado — a fonte do menu mudou de lugar?');

        $fonte = (string) file_get_contents($tsPath);

        // Pega só o bloco de MENU_PUBLICO: o arquivo tem também os tipos e as
        // funções, e `rotulo:` aparece nas assinaturas deles.
        $inicio = strpos($fonte, 'export const MENU_PUBLICO');
        $this->assertNotFalse($inicio, 'MENU_PUBLICO não encontrado em menuPublico.ts.');
        $bloco = substr($fonte, $inicio);
        $fim = strpos($bloco, '];');
        $this->assertNotFalse($fim, 'Fim da lista MENU_PUBLICO não encontrado — o formato do arquivo mudou?');
        $bloco = substr($bloco, 0, $fim);

        preg_match_all("/rotulo:\s*'([^']+)'/", $bloco, $rotulos);
        preg_match_all("/view:\s*'([^']+)'/", $bloco, $views);

        $rotulosTs = $rotulos[1];
        $this->assertNotEmpty($rotulosTs, 'Nenhum rótulo encontrado em menuPublico.ts — o formato do arquivo mudou?');

        /** @var list<array<string, mixed>> $php */
        $php = config('menu_publico');
        $rotulosPhp = [];
        $viewsPhp = [];

        foreach ($php as $entrada) {
            $rotulosPhp[] = (string) $entrada['rotulo'];

            if ($entrada['tipo'] === 'link') {
                $viewsPhp[] = (string) $entrada['rota'];

                continue;
            }

            foreach ($entrada['itens'] as $item) {
                $rotulosPhp[] = (string) $item['rotulo'];
                $viewsPhp[] = (string) $item['rota'];
            }
        }

        $this->assertSame(
            $rotulosTs,
            $rotulosPhp,
            'config/menu_publico.php divergiu de src/config/menuPublico.ts nos rótulos ou na ordem — sincronize os dois.',
        );

        // No TS o destino é o nome da view (`o-ava`); no PHP é o caminho
        // (`/o-ava`). `PORTAL_PATHS` faz a mesma tradução do lado React, e é ela
        // que esta linha confere — sem ela, um item poderia apontar para telas
        // diferentes nas duas tecnologias com os mesmos rótulos.
        $this->assertSame(
            array_map(static fn (string $view): string => '/'.$view, $views[1]),
            $viewsPhp,
            'Os destinos do menu divergiram entre PHP e TS.',
        );
    }
}
