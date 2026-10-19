<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Cabeçalho e rodapé institucionais das telas em Blade (fase M2 do plano 16).
 *
 * O Documento D §2.5 torna marca, header mínimo e footer obrigatórios na área
 * pública, e até a M1 as telas migradas saíam sem os três. O que este arquivo
 * protege não é a aparência: é que a navegação exista, que ela aponte para os
 * mesmos lugares do menu e que o estado "você está aqui" não dependa só de cor.
 */
final class CabecalhoRodapeBladeTest extends TestCase
{
    /** Telas migradas que usam o layout público. */
    private const TELAS = ['/o-ava', '/o-projeto', '/orientacoes', '/duvidas'];

    public function test_toda_tela_migrada_tem_marca_menu_e_rodape(): void
    {
        foreach (self::TELAS as $rota) {
            $resposta = $this->get($rota);

            $resposta->assertOk();
            $resposta->assertSee('AVASEC', escape: false);
            $resposta->assertSee('Navegação principal', escape: false);
            $resposta->assertSee('Todos os direitos reservados', escape: false);

            // O atalho de teclado é o primeiro foco da página; sem ele, quem
            // navega por teclado percorre o menu inteiro em toda tela.
            $resposta->assertSee('Pular para o conteúdo', escape: false);
            $resposta->assertSee('id="conteudo"', escape: false);
        }
    }

    public function test_menu_leva_a_todos_os_destinos_configurados(): void
    {
        $resposta = $this->get('/o-ava');

        /** @var list<array<string, mixed>> $menu */
        $menu = config('menu_publico');

        foreach ($menu as $entrada) {
            $resposta->assertSee($entrada['rotulo'], escape: false);

            if ($entrada['tipo'] === 'link') {
                $resposta->assertSee('href="'.$entrada['rota'].'"', escape: false);

                continue;
            }

            foreach ($entrada['itens'] as $item) {
                $resposta->assertSee('href="'.$item['rota'].'"', escape: false);
            }
        }
    }

    /**
     * A skill `redesenhar-ava-lms` proíbe marcar o item ativo só pela cor da
     * fonte. Aqui isso é conferido pelo que não depende de visão de cor:
     * `aria-current`, que é o que o leitor de tela anuncia.
     */
    public function test_item_ativo_e_anunciado_e_nao_depende_so_de_cor(): void
    {
        $resposta = $this->get('/o-ava');

        $resposta->assertSee('aria-current="page"', escape: false);
        // O fundo lilás é o sinal visual que acompanha o `aria-current`.
        $resposta->assertSee('bg-ava-lilas-fundo', escape: false);
    }

    /**
     * Em Notícias quem acende é o GRUPO "A Escola", não a página — é a regra do
     * `entradaAtiva` do React, e ela erra em silêncio (o item simplesmente não
     * acende) quando alguém passa o rótulo errado ao layout.
     */
    public function test_pagina_de_dentro_de_grupo_acende_o_grupo(): void
    {
        foreach (['/o-ava' => 'A Escola', '/o-projeto' => 'A Escola', '/duvidas' => 'Ajuda', '/orientacoes' => 'Ajuda'] as $rota => $grupo) {
            $html = $this->get($rota)->getContent();
            $this->assertIsString($html);

            $posicaoDoGrupo = strpos($html, '<span>'.$grupo.'</span>');
            $this->assertNotFalse($posicaoDoGrupo, "Grupo {$grupo} não encontrado no cabeçalho de {$rota}.");

            // O `aria-current` do botão vem ANTES do rótulo dele na marcação.
            $trecho = substr($html, max(0, $posicaoDoGrupo - 900), 900);
            $this->assertStringContainsString(
                'aria-current="page"',
                $trecho,
                "Em {$rota} o grupo {$grupo} deveria estar marcado como atual.",
            );
        }
    }

    /**
     * Entrar, Cadastre-se e Acessibilidade apontam para as janelas que ainda
     * vivem no React. Se alguém remover o `?janela=` do App.tsx sem trocar
     * estes destinos, o botão principal do portal passa a não fazer nada.
     */
    public function test_portas_para_as_janelas_que_ainda_sao_react(): void
    {
        $resposta = $this->get('/o-ava');

        $resposta->assertSee('href="/?janela=login"', escape: false);
        $resposta->assertSee('href="/?janela=cadastro"', escape: false);
        $resposta->assertSee('href="/?janela=acessibilidade"', escape: false);
    }
}
