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
            $resposta->assertSee('Escola Estadual', escape: false);
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

    /** Entrar e Cadastre-se apontam para as telas em Blade (fase M2). */
    public function test_as_portas_de_entrada_apontam_para_as_telas_certas(): void
    {
        $resposta = $this->get('/o-ava');

        $resposta->assertSee('href="/entrar"', escape: false);
        $resposta->assertSee('href="/cadastro"', escape: false);
    }

    /**
     * Nenhum link do portal aponta para uma janela do SPA.
     *
     * `?janela=` dependia de um efeito do React que rodava nas páginas públicas
     * servidas por ele. Desde que a última delas passou para Blade (20/10/2026)
     * não resta nenhuma, e esses links passaram a levar a lugar nenhum — o caso
     * concreto era "Acessibilidade" no rodapé.
     *
     * O item saiu por decisão do responsável, e o painel fica para quando
     * existir em Blade (plano 18, §18.4). Este teste existe para o link não
     * voltar por distração: quem precisa do recurso clicaria, nada aconteceria,
     * e concluiria que o recurso não funciona.
     */
    public function test_nenhum_link_publico_aponta_para_janela_do_spa(): void
    {
        foreach (['/', '/o-ava', '/cursos', '/noticias', '/certificados', '/calendario', '/duvidas'] as $rota) {
            $this->get($rota)->assertDontSee('janela=', escape: false);
        }
    }

    /**
     * A marca do topo é o nome da escola, sem o logotipo "AVASEC" (decisão
     * do responsável, 20/10/2026).
     *
     * Confere DENTRO do `<header>`: a palavra continua existindo no rodapé e
     * no título da aba, e uma busca na página inteira passaria sem provar nada
     * sobre o topo.
     */
    public function test_a_marca_do_topo_e_o_nome_da_escola_e_nao_avasec(): void
    {
        foreach (['/', ...self::TELAS] as $rota) {
            $html = (string) $this->get($rota)->getContent();
            $inicio = strpos($html, '<header');
            $fim = strpos($html, '</header>');
            $this->assertNotFalse($inicio, "Sem <header> em {$rota}.");
            $this->assertNotFalse($fim, "Sem </header> em {$rota}.");

            $cabecalho = substr($html, $inicio, $fim - $inicio);

            $this->assertStringContainsString('Escola Estadual', $cabecalho, "Marca sem o nome em {$rota}.");
            $this->assertStringContainsString('da Cultura RJ', $cabecalho, "Marca sem o nome em {$rota}.");
            $this->assertStringNotContainsString('AVASEC', $cabecalho, "O logotipo AVASEC voltou ao topo em {$rota}.");
        }
    }
}
