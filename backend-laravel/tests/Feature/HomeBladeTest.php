<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Usuario;
use App\Support\Jwt;
use Tests\TestCase;

/**
 * Página inicial em Blade — tela 1 do redesenho (plano 18, fase M2).
 *
 * Era a última tela do portal público servida pelo React, e esperava o login
 * existir como endereço: enquanto o modal de entrar morasse dentro do SPA em
 * `/`, migrar `/` deixaria o sistema sem porta de entrada.
 *
 * O miolo deste arquivo são as **ausências**. A skill manda a página terminar
 * no destaque Patrimônio Vivo, e duas seções caíram com isso — uma por remoção
 * explícita, outra por mudança de endereço. Só um teste impede que voltem.
 */
final class HomeBladeTest extends TestCase
{
    public function test_a_raiz_responde_em_blade_e_nao_pelo_react(): void
    {
        $resposta = $this->get('/');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', escape: false);
    }

    public function test_traz_o_heroi_a_faixa_de_dados_e_o_patrimonio_vivo(): void
    {
        $resposta = $this->get('/');

        $resposta->assertSee('Inscrições Abertas — Cursos Livres de Qualificação', escape: false);
        $resposta->assertSee('AVASEC em dados', escape: false);
        $resposta->assertSee('Patrimônio Vivo', escape: false);
        $resposta->assertSee('Fazer Artístico Decolonial', escape: false);

    }

    /**
     * Os quatro cartões de números (188K, 300K, 66K, 4M+) saíram por decisão do
     * responsável em 26/10: eram texto fixo, sem consulta por trás. Só um teste
     * impede que voltem.
     */
    public function test_nao_traz_os_cartoes_de_numeros(): void
    {
        $resposta = $this->get('/');

        foreach (['188K', '300K', '66K', '4M+', 'Estudantes cadastrados', 'Visitas à plataforma'] as $antigo) {
            $resposta->assertDontSee($antigo, escape: false);
        }
    }

    /**
     * "Sentiu falta de algum curso?" foi removido por inteiro — ícone, texto,
     * campo e botão — e nada ocupa o lugar. É remoção explícita da skill.
     */
    public function test_nao_traz_a_faixa_de_sugestao_de_curso(): void
    {
        $resposta = $this->get('/');

        $resposta->assertDontSee('Sentiu falta de algum curso', escape: false);
        $resposta->assertDontSee('Sugira um tema', escape: false);
        $resposta->assertDontSee('Sugerir', escape: false);
    }

    /**
     * A página termina no Patrimônio Vivo: a seção dos três princípios ficava
     * depois dele e saiu daqui.
     */
    public function test_a_pagina_termina_no_patrimonio_vivo(): void
    {
        $resposta = $this->get('/');

        $resposta->assertDontSee('A educação que orienta esta escola', escape: false);
        $resposta->assertDontSee('Nossa concepção', escape: false);
    }

    /**
     * ...mas o texto não foi apagado: ele era o único lugar do site onde
     * existia, e mudou de endereço. Tirar do lugar não é tirar a função.
     */
    public function test_o_texto_que_saiu_da_home_esta_em_o_projeto(): void
    {
        $resposta = $this->get('/o-projeto');

        $resposta->assertSee('A educação que orienta esta escola', escape: false);
        $resposta->assertSee('Ensinar é uma prática que começa pelo que o estudante já sabe', escape: false);

        foreach (['Centrada em quem aprende', 'Diálogo, não transmissão', 'Leitura crítica'] as $principio) {
            $resposta->assertSee($principio, escape: false);
        }
    }

    public function test_visitante_ve_entrar_e_cadastre_se(): void
    {
        $resposta = $this->get('/');

        $resposta->assertSee('href="/entrar"', escape: false);
        $resposta->assertSee('href="/cadastro"', escape: false);
        $resposta->assertDontSee('Meu painel', escape: false);
    }

    /**
     * Quem já entrou não pode ler "Entrar" na home — concluiria que a sessão
     * caiu. O cabeçalho do React trocava os botões pelo atalho ao painel, e o
     * de Blade faz o mesmo.
     */
    public function test_quem_tem_sessao_ve_o_atalho_para_o_proprio_painel(): void
    {
        $aluno = Usuario::query()
            ->where('papel', 'student')->where('status', 'active')->whereNull('inativado_em')->first();

        if ($aluno === null) {
            $this->markTestSkipped('Sem aluno ativo no banco de desenvolvimento.');
        }

        $resposta = $this
            ->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, Jwt::issue((string) $aluno->id, (string) $aluno->nome, 'student'))
            ->get('/');

        $resposta->assertSee('Meu painel', escape: false);
        $resposta->assertSee('href="/aluno"', escape: false);
        $resposta->assertDontSee('Cadastre-se', escape: false);
    }

    /**
     * Sessão inválida é visitante, e não erro: a home é pública e tem de abrir
     * para qualquer um, com qualquer cookie estragado no navegador.
     */
    public function test_cookie_estragado_nao_derruba_a_home(): void
    {
        $resposta = $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, 'isto.nao.e.um.jwt')->get('/');

        $resposta->assertOk();
        $resposta->assertSee('href="/entrar"', escape: false);
    }
}
