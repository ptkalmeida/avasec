<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SitePageContentService;
use Tests\TestCase;

/**
 * As páginas institucionais já migradas para Blade (fase M1 do plano 16).
 *
 * Este arquivo é o substituto PHPUnit dos testes Vitest de componente, conforme
 * a tabela da §5 do plano: uma tela só sai do React quando o teste equivalente
 * estiver verde. O que ele protege é a **invariante 1** — migrar a tela não
 * muda o que ela mostra.
 *
 * Por isso nenhum texto é escrito aqui à mão. O teste pergunta ao
 * `SitePageContentService` o que a página deve dizer e confere que a resposta
 * HTTP diz aquilo. Um texto fixo no teste provaria apenas que alguém digitou a
 * mesma frase duas vezes; e, se o admin editasse o conteúdo, passaria a falhar
 * sem que nada estivesse errado.
 *
 * O `RoteadorMpaTest` cuida de outra pergunta — se a rota é atendida pelo
 * Laravel e se está no contrato do proxy. Aqui a pergunta é o conteúdo.
 */
final class PaginasPublicasBladeTest extends TestCase
{
    /**
     * Rota → chave da página no serviço de conteúdo.
     *
     * Acrescentar uma linha aqui é o que cobre cada nova tela da M1. `/duvidas`
     * não está na lista do cabeçalho porque seu corpo é um componente Livewire,
     * que não sai no HTML da primeira resposta; ela é coberta mais abaixo.
     *
     * @var array<string, string>
     */
    private const TELAS = [
        '/o-ava' => 'o-ava',
        '/o-projeto' => 'o-projeto',
        '/orientacoes' => 'orientacoes',
        '/noticias' => 'noticias',
    ];

    public function test_cada_tela_migrada_responde_em_blade_e_nao_pelo_react(): void
    {
        foreach (array_keys(self::TELAS) as $rota) {
            $resposta = $this->get($rota);

            $resposta->assertOk();
            // O casco do React traz a âncora onde ele se monta; a tela Blade, não.
            $resposta->assertDontSee('<div id="root">', false);
        }
    }

    public function test_cada_tela_mostra_o_conteudo_do_mesmo_servico_que_alimentava_o_react(): void
    {
        $servico = app(SitePageContentService::class);

        foreach (self::TELAS as $rota => $chave) {
            $pagina = $servico->get($chave);
            $resposta = $this->get($rota);

            /*
             * `e()` no valor esperado, e não `escape: true` na asserção: o
             * título "Notícias & Novidades" sai da view como `&amp;`, que é o
             * correto, e a comparação crua falharia por causa da escapagem —
             * acusando a tela de não mostrar um texto que ela mostra.
             */
            $resposta->assertSee(e($pagina['title']), false);
            $resposta->assertSee(e($pagina['eyebrow']), false);

            foreach ($pagina['items'] as $item) {
                $resposta->assertSee(e($item['title']), false);
                $resposta->assertSee(e($item['description']), false);
            }
        }
    }

    /**
     * O título da página é `<h1>`.
     *
     * As nove páginas institucionais não tinham nenhum `<h1>` antes do
     * redesenho: para quem navega por cabeçalhos, o título da página era
     * anunciado como subsubtítulo. A correção veio na fase 13 e a migração não
     * pode desfazê-la em silêncio.
     */
    public function test_o_titulo_da_pagina_e_um_h1(): void
    {
        $servico = app(SitePageContentService::class);

        foreach (self::TELAS as $rota => $chave) {
            $titulo = $servico->get($chave)['title'];
            $html = $this->get($rota)->getContent();

            $this->assertMatchesRegularExpression(
                '/<h1[^>]*>\s*'.preg_quote(e((string) $titulo), '/').'\s*<\/h1>/u',
                (string) $html,
                "O título de {$rota} precisa sair em <h1>.",
            );
        }
    }

    /**
     * Sem `tema-ava` no `<html>`, as regras da variante `ava:` ficam inertes —
     * foi assim que o Perfil do admin passou a rolar na horizontal em 320px.
     */
    public function test_o_tema_esta_ligado_em_toda_tela_migrada(): void
    {
        $rotas = [...array_keys(self::TELAS), '/duvidas'];

        foreach ($rotas as $rota) {
            $this->get($rota)->assertSee('class="tema-ava"', false);
        }
    }

    /**
     * O endereço da imagem do "O Projeto" é editável pelo admin e aponta para
     * fora. Sem `referrerpolicy`, o navegador conta ao servidor de destino de
     * qual página do portal a pessoa veio.
     */
    public function test_a_imagem_externa_do_projeto_nao_vaza_a_origem(): void
    {
        $this->get('/o-projeto')->assertSee('referrerpolicy="no-referrer"', false);
    }

    /**
     * A numeração das diretrizes vem da ordem, não do conteúdo: o admin edita
     * título e descrição, nunca o algarismo.
     */
    public function test_as_diretrizes_sao_numeradas_em_romano_pela_ordem(): void
    {
        $total = count(app(SitePageContentService::class)->get('orientacoes')['items']);
        $resposta = $this->get('/orientacoes');

        foreach (array_slice(['I', 'II', 'III', 'IV', 'V', 'VI'], 0, $total) as $romano) {
            $resposta->assertSee("Diretriz {$romano}", false);
        }
    }

    /**
     * A busca do cabeçalho leva a `/noticias?busca=`, e quem chega com o termo
     * na URL precisa ver a lista já filtrada — do contrário o link promete um
     * recorte e entrega a página inteira.
     */
    public function test_a_busca_na_url_recorta_a_lista_de_noticias(): void
    {
        $itens = app(SitePageContentService::class)->get('noticias')['items'];

        if (count($itens) < 2) {
            $this->markTestSkipped('É preciso mais de uma notícia para provar o recorte.');
        }

        // Uma palavra que existe no título da primeira e não nas outras.
        $palavra = null;
        foreach (preg_split('/\s+/u', (string) $itens[0]['title']) as $candidata) {
            $candidata = trim((string) $candidata, " 	.,:;\"'()");
            if (mb_strlen($candidata) < 5) {
                continue;
            }

            $apareceNoutra = false;
            foreach (array_slice($itens, 1) as $outra) {
                $alvo = ($outra['title'] ?? '').' '.($outra['description'] ?? '');
                if (mb_stripos($alvo, $candidata) !== false) {
                    $apareceNoutra = true;
                    break;
                }
            }

            if (! $apareceNoutra) {
                $palavra = $candidata;
                break;
            }
        }

        if ($palavra === null) {
            $this->markTestSkipped('As notícias do banco não têm palavra exclusiva para filtrar.');
        }

        $resposta = $this->get('/noticias?busca='.urlencode($palavra));

        $resposta->assertOk();
        $resposta->assertSee(e((string) $itens[0]['title']), false);
        $resposta->assertDontSee(e((string) $itens[1]['title']), false);
    }

    /**
     * Busca sem resultado mostra o que se procurou e oferece a saída. Devolver
     * a grade vazia deixaria a pessoa achando que não há notícia nenhuma.
     */
    public function test_busca_sem_resultado_explica_e_oferece_como_limpar(): void
    {
        $resposta = $this->get('/noticias?busca=zzzznaoexiste');

        $resposta->assertOk();
        $resposta->assertSee('Sem resultados', false);
        $resposta->assertSee('zzzznaoexiste', false);
        $resposta->assertSee('href="/noticias"', false);
    }

    /**
     * As imagens das notícias são endereços externos editáveis pelo admin —
     * mesma razão do "O Projeto": sem `referrerpolicy`, o servidor de destino
     * fica sabendo de qual página do portal a pessoa veio.
     */
    public function test_as_imagens_das_noticias_nao_vazam_a_origem(): void
    {
        $temImagem = false;
        foreach (app(SitePageContentService::class)->get('noticias')['items'] as $item) {
            if (! empty($item['image'])) {
                $temImagem = true;
                break;
            }
        }

        if (! $temImagem) {
            $this->markTestSkipped('Nenhuma notícia do banco tem imagem.');
        }

        $this->get('/noticias')->assertSee('referrerpolicy="no-referrer"', false);
    }

    /** O convite para entrar é para quem ainda não entrou. */
    public function test_o_convite_para_entrar_aparece_para_visitante(): void
    {
        $this->get('/noticias')->assertSee('Entrar para ler o informativo', false);
    }
}
