<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * O que acontece com um endereço que nenhuma rota atende (ADR 020, fase M7).
 *
 * Até a M7 esse endereço caía num fallback que entregava o SPA React, e dois
 * defeitos reais nasceram daí: `/uploads/../../.env` passava a responder 200 com
 * a página do React (a recusa continuava, mas ficava invisível), e um endereço
 * sem tela migrada abria em branco. Sem SPA, a regra ficou simples: 404.
 */
final class EnderecoDesconhecidoTest extends TestCase
{
    public function test_rota_registrada_e_atendida_em_blade(): void
    {
        $resposta = $this->get('/o-ava');

        $resposta->assertOk();
        $resposta->assertSee('O que é o AVA?', false);
        $resposta->assertDontSee('<div id="root">', false);
    }

    public function test_endereco_desconhecido_e_404_com_pagina_propria(): void
    {
        $resposta = $this->get('/uma-tela-que-nunca-existiu');

        $resposta->assertNotFound();
        $resposta->assertSee('Página não encontrada', false);
        $resposta->assertSee('Voltar ao início', false);
        $resposta->assertDontSee('<div id="root">', false);
    }

    public function test_a_pagina_404_nao_vaza_detalhe_do_servidor(): void
    {
        $html = $this->get('/uma-tela-que-nunca-existiu')->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString(base_path(), $html);
        $this->assertStringNotContainsString('Stack trace', $html);
        $this->assertStringNotContainsString('Illuminate\\', $html);
    }

    /** Sob `/api/` o erro continua sendo o JSON do contrato, nunca HTML. */
    public function test_caminho_inexistente_na_api_devolve_json(): void
    {
        $resposta = $this->getJson('/api/rota-que-nao-existe');

        $resposta->assertNotFound();
        $resposta->assertHeader('Content-Type', 'application/json');
    }

    /**
     * Recurso do SERVIDOR inexistente é 404 visível: se virasse 200, uma varredura
     * de segurança leria a recusa como sucesso.
     */
    public function test_recurso_do_servidor_inexistente_e_404(): void
    {
        foreach ([
            '/uploads/'.rawurlencode('../../.env'),
            '/uploads/arquivo-que-nao-existe.png',
            '/health/inexistente',
            '/sistema/inexistente',
        ] as $caminho) {
            $this->assertSame(404, $this->get($caminho)->getStatusCode(), "{$caminho} devia ser 404.");
        }
    }
}
