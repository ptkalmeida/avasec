<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Catálogo público em Blade — tela 2 do redesenho (plano 18, fase M2).
 *
 * Este arquivo protege as decisões da skill que são fáceis de desfazer sem
 * querer: o número de cursos, o título e a **ausência** dos controles
 * removidos. Teste de ausência é incomum, e aqui é o principal: a skill manda
 * não reintroduzir busca nem filtro "sem nova solicitação", e nada além de um
 * teste impede alguém de trazê-los de volta por reflexo.
 */
final class CatalogoPublicoBladeTest extends TestCase
{
    public function test_responde_em_blade_e_nao_pelo_react(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertOk();
        // A âncora onde o React se monta não existe numa tela Blade.
        $resposta->assertDontSee('id="root"', escape: false);
    }

    public function test_titulo_e_o_aprovado_pela_skill(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertSee('Cursos em destaque', escape: false);
        $resposta->assertDontSee('Cursos Disponíveis', escape: false);
    }

    public function test_mostra_exatamente_quatro_cursos_os_primeiros_da_vitrine(): void
    {
        /** @var list<array<string, string>> $vitrine */
        $vitrine = config('catalogo_publico');
        $this->assertGreaterThan(4, count($vitrine), 'A vitrine precisa ter mais de 4 itens para este teste significar algo.');

        $resposta = $this->get('/cursos');
        $html = (string) $resposta->getContent();

        foreach (array_slice($vitrine, 0, 4) as $curso) {
            $resposta->assertSee($curso['title'], escape: false);
        }

        foreach (array_slice($vitrine, 4) as $curso) {
            $this->assertStringNotContainsString(
                e($curso['title']),
                $html,
                'A tela mostrou um curso além dos quatro em destaque.',
            );
        }
    }

    /**
     * A skill removeu a busca e os filtros do catálogo e diz, com todas as
     * letras, para não reintroduzi-los sem nova solicitação.
     */
    public function test_nao_tem_busca_propria_nem_filtro_por_categoria(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertDontSee('Buscar curso pelo nome', escape: false);
        $resposta->assertDontSee('Filtrar por categoria', escape: false);
        $resposta->assertDontSee('Limpar Filtros', escape: false);
        // O texto de abertura não pode voltar a prometer filtros que não existem.
        $resposta->assertDontSee('Use os filtros', escape: false);
    }

    public function test_tem_o_botao_veja_mais_cursos_apontando_para_o_login(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertSee('Veja mais cursos', escape: false);
        $resposta->assertSee('href="/?janela=login"', escape: false);
    }

    /**
     * A tela não pode afirmar uma ordenação que a vitrine não sustenta: não há
     * data de publicação nela. A skill prevê o caso e manda não afirmar.
     */
    public function test_nao_afirma_que_os_cursos_sao_os_mais_recentes(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertDontSee('mais recentes', escape: false);
        $resposta->assertDontSee('Mais recentes', escape: false);
    }
}
