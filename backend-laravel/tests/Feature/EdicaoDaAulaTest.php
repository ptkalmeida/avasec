<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ConteudoDaAula;
use App\Support\EdicaoDaAula;
use PHPUnit\Framework\TestCase;

/**
 * A metade de escrita de `lessonContent.ts`, em PHP.
 *
 * Repete os casos de `tests/frontend/lessonContent.test.ts`. A concordância com
 * o TypeScript foi conferida à parte, sobre as 82 aulas reais do banco e casos
 * de borda (5.184 comparações, zero divergências); este arquivo guarda o que
 * pode regredir sem depender do banco.
 */
final class EdicaoDaAulaTest extends TestCase
{
    private const CONTEUDO = "## Primeira seção\n\nUm parágrafo qualquer.\n\n1. um\n2. dois\n\nCódigo 1: exemplo:\n```java\nint a = 1;\n```\n\nFigura 1 - o diagrama\n![Diagrama das classes](/uploads/diagrama.png)\n\n@[Demonstração](https://youtu.be/abc123)\n\n### Fim";

    private const AULA = "### Estruturando o seu AVA\nA arquitetura de informação organiza os conteúdos.\n\n- Card Sorting\n- Sitemaps\n\nFigura 1 - Organograma\n![organograma](/uploads/org.png)";

    /** @return list<string> */
    private function tipos(string $conteudo): array
    {
        return array_column(ConteudoDaAula::analisar($conteudo)['blocos'], 'tipo');
    }

    /** @return list<array<string, mixed>> */
    private function blocos(string $conteudo): array
    {
        return ConteudoDaAula::analisarComTrechos($conteudo)['blocos'];
    }

    /** @return list<string> */
    private function serializados(string $conteudo): array
    {
        return array_map(EdicaoDaAula::serializar(...), $this->blocos($conteudo));
    }

    // ---- o trecho ------------------------------------------------------------

    public function test_cada_trecho_aponta_exatamente_para_o_texto_que_gerou_o_bloco(): void
    {
        // É essa igualdade que permite editar UM bloco sem reescrever o resto.
        foreach ($this->blocos(self::CONTEUDO) as $bloco) {
            $this->assertSame(
                EdicaoDaAula::serializar($bloco),
                substr(self::CONTEUDO, $bloco['inicio'], $bloco['fim'] - $bloco['inicio']),
            );
        }
    }

    public function test_o_trecho_do_codigo_inclui_a_legenda(): void
    {
        $codigo = array_values(array_filter($this->blocos(self::CONTEUDO), static fn (array $b): bool => $b['tipo'] === 'codigo'))[0];
        $recorte = substr(self::CONTEUDO, $codigo['inicio'], $codigo['fim'] - $codigo['inicio']);

        $this->assertStringContainsString('Código 1: exemplo:', $recorte);
        $this->assertStringContainsString('int a = 1;', $recorte);
    }

    public function test_a_leitura_publica_continua_sem_os_trechos(): void
    {
        foreach (ConteudoDaAula::analisar(self::CONTEUDO)['blocos'] as $bloco) {
            $this->assertArrayNotHasKey('inicio', $bloco);
            $this->assertArrayNotHasKey('fim', $bloco);
        }
    }

    public function test_trecho_com_acento_e_emoji_e_recortado_inteiro(): void
    {
        $c = "Texto ção 😀\n\n## Título ção 😀\n\n- item 😀";

        foreach ($this->blocos($c) as $bloco) {
            $this->assertSame(
                EdicaoDaAula::serializar($bloco),
                substr($c, $bloco['inicio'], $bloco['fim'] - $bloco['inicio']),
            );
        }
    }

    // ---- substituir, remover, inserir -----------------------------------------

    public function test_troca_um_bloco_sem_tocar_no_restante(): void
    {
        $paragrafo = array_values(array_filter($this->blocos(self::CONTEUDO), static fn (array $b): bool => $b['tipo'] === 'paragrafo'))[0];

        $novo = EdicaoDaAula::substituir(self::CONTEUDO, $paragrafo['inicio'], $paragrafo['fim'], 'Texto trocado.');

        $this->assertStringContainsString('Texto trocado.', $novo);
        $this->assertStringNotContainsString('Um parágrafo qualquer.', $novo);
        $this->assertStringContainsString('## Primeira seção', $novo);
        $this->assertStringContainsString('```java', $novo);
        $this->assertStringContainsString('### Fim', $novo);
    }

    public function test_remove_o_bloco_sem_deixar_buraco_de_linhas_em_branco(): void
    {
        $blocos = $this->blocos(self::CONTEUDO);
        $lista = array_values(array_filter($blocos, static fn (array $b): bool => $b['tipo'] === 'listaNumerada'))[0];

        $novo = EdicaoDaAula::remover(self::CONTEUDO, $lista['inicio'], $lista['fim']);

        $this->assertStringNotContainsString('1. um', $novo);
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $novo);
        $this->assertCount(count($blocos) - 1, $this->blocos($novo));
    }

    public function test_remover_a_imagem_tira_a_legenda_junto(): void
    {
        $c = "Um parágrafo.\n\nFigura 1 - o diagrama\n![Diagrama](/uploads/x.png)\n\nOutro parágrafo.";
        $imagem = array_values(array_filter($this->blocos($c), static fn (array $b): bool => $b['tipo'] === 'imagem'))[0];

        $novo = EdicaoDaAula::remover($c, $imagem['inicio'], $imagem['fim']);

        $this->assertStringNotContainsString('Figura 1', $novo);
        $this->assertStringNotContainsString('/uploads/x.png', $novo);
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $novo);
        $this->assertCount(2, $this->blocos($novo));
    }

    public function test_insere_bloco_novo_depois_do_indicado(): void
    {
        $primeiro = $this->blocos(self::CONTEUDO)[0];

        $novo = EdicaoDaAula::inserirEm(self::CONTEUDO, $primeiro['fim'], '### Recém-criado');

        $segundo = ConteudoDaAula::analisar($novo)['blocos'][1];
        $this->assertSame('subsecao', $segundo['tipo']);
        $this->assertSame('Recém-criado', $segundo['texto']);
    }

    public function test_inserir_no_comeco_nao_deixa_linhas_em_branco_sobrando(): void
    {
        $this->assertSame("NOVO\n\n## A", EdicaoDaAula::inserirEm('## A', 0, 'NOVO'));
        $this->assertSame("NOVO\n\n## A", EdicaoDaAula::inserirEm("\n\n## A", 0, 'NOVO'));
    }

    public function test_primeiro_bloco_de_conteudo_vazio_nao_vem_com_linhas_sobrando(): void
    {
        $this->assertSame('## Começo', EdicaoDaAula::inserirEm('', 0, '## Começo'));
        $this->assertSame('## Começo', EdicaoDaAula::inserirEm("  \n ", 0, '## Começo'));
    }

    public function test_posicao_fora_do_texto_e_limitada(): void
    {
        $this->assertSame("## A\n\nNOVO", EdicaoDaAula::inserirEm('## A', 999999, 'NOVO'));
        $this->assertSame("NOVO\n\n## A", EdicaoDaAula::inserirEm('## A', -5, 'NOVO'));
    }

    // ---- serializar ------------------------------------------------------------

    public function test_serializar_e_reler_a_midia_devolve_o_mesmo_bloco(): void
    {
        $casos = [
            '![Diagrama](/uploads/x.png)',
            "Figura 2: o fluxo\n![Fluxo](/uploads/y.png)",
            '@[Aula gravada](https://youtu.be/abc123)',
            "Vídeo 1 - a demonstração\n@[Demo](https://youtu.be/abc123)",
        ];

        foreach ($casos as $caso) {
            $this->assertSame($caso, EdicaoDaAula::serializar($this->blocos($caso)[0]));
        }
    }

    public function test_serializar_remove_colchete_da_descricao_para_a_releitura_nao_quebrar(): void
    {
        $texto = EdicaoDaAula::serializar([
            'tipo' => 'imagem', 'url' => '/uploads/x.png', 'alt' => 'Figura [1] do livro', 'legenda' => null,
        ]);

        $this->assertSame('![Figura 1 do livro](/uploads/x.png)', $texto);

        $lido = ConteudoDaAula::analisar($texto)['blocos'][0];
        $this->assertSame('imagem', $lido['tipo']);
        $this->assertSame('Figura 1 do livro', $lido['alt']);
    }

    public function test_grava_o_marcador_so_quando_ha_formatacao(): void
    {
        $this->assertSame('A.', EdicaoDaAula::serializar(['tipo' => 'paragrafo', 'texto' => 'A.', 'tamanho' => 'normal', 'alinhamento' => 'esquerda']));
        $this->assertSame('{centro} A.', EdicaoDaAula::serializar(['tipo' => 'paragrafo', 'texto' => 'A.', 'alinhamento' => 'centro']));
        $this->assertSame('{direita pequeno} A.', EdicaoDaAula::serializar(['tipo' => 'paragrafo', 'texto' => 'A.', 'tamanho' => 'pequeno', 'alinhamento' => 'direita']));
        $this->assertSame('### {direita} T', EdicaoDaAula::serializar(['tipo' => 'subsecao', 'texto' => 'T', 'alinhamento' => 'direita']));
    }

    public function test_paragrafo_de_varias_linhas_leva_o_marcador_em_todas(): void
    {
        $this->assertSame("{centro} a\n{centro} b", EdicaoDaAula::serializar(['tipo' => 'paragrafo', 'texto' => "a\nb", 'alinhamento' => 'centro']));
    }

    public function test_tipo_desconhecido_e_recusado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EdicaoDaAula::serializar(['tipo' => 'tabela']);
    }

    /** Ler → serializar → ler dá os mesmos blocos: a edição nunca muda o que o aluno vê. */
    public function test_ida_e_volta_nao_muda_nenhum_bloco(): void
    {
        foreach ([self::CONTEUDO, self::AULA, "{centro grande} a\n### {direita} b\n```\nx\n```\n1. a\n2. b\n- c"] as $c) {
            $reler = implode("\n\n", $this->serializados($c));

            $this->assertSame($this->serializados($c), $this->serializados($reler), $c);
            $this->assertSame($this->tipos($c), $this->tipos($reler), $c);
        }
    }

    // ---- mover -------------------------------------------------------------------

    public function test_sobe_a_figura_com_a_legenda_junto(): void
    {
        $novo = EdicaoDaAula::mover(self::AULA, 3, -1);

        $this->assertSame(['subsecao', 'paragrafo', 'imagem', 'listaMarcadores'], $this->tipos($novo));
        $figura = ConteudoDaAula::analisar($novo)['blocos'][2];
        $this->assertSame('Figura 1 - Organograma', $figura['legenda']);
        $this->assertSame('organograma', $figura['alt']);
    }

    public function test_desce_um_trecho(): void
    {
        $this->assertSame(['paragrafo', 'subsecao', 'listaMarcadores', 'imagem'], $this->tipos(EdicaoDaAula::mover(self::AULA, 0, 1)));
    }

    public function test_trechos_colados_por_uma_quebra_so_nao_se_fundem_ao_trocar(): void
    {
        $novo = EdicaoDaAula::mover(self::AULA, 0, 1);

        $this->assertStringStartsWith("A arquitetura de informação organiza os conteúdos.\n\n### Estruturando o seu AVA", $novo);
    }

    public function test_nas_pontas_e_com_indice_invalido_nao_faz_nada(): void
    {
        $this->assertSame(self::AULA, EdicaoDaAula::mover(self::AULA, 0, -1));
        $this->assertSame(self::AULA, EdicaoDaAula::mover(self::AULA, 3, 1));
        $this->assertSame(self::AULA, EdicaoDaAula::mover(self::AULA, 9, -1));
        $this->assertSame(self::AULA, EdicaoDaAula::mover(self::AULA, -1, 1));
        $this->assertSame(self::AULA, EdicaoDaAula::mover(self::AULA, 1, 2));
        $this->assertSame('', EdicaoDaAula::mover('', 0, 1));
    }

    public function test_subir_e_descer_de_volta_devolve_a_mesma_aula(): void
    {
        $idaEVolta = EdicaoDaAula::mover(EdicaoDaAula::mover(self::AULA, 2, 1), 3, -1);

        $this->assertSame($this->serializados(self::AULA), $this->serializados($idaEVolta));
    }

    public function test_secoes_trocam_de_lugar_e_renumeram_pela_nova_ordem(): void
    {
        $novo = EdicaoDaAula::mover("## Primeira\n\nTexto um.\n\n## Segunda", 2, -1);

        $secoes = ConteudoDaAula::analisar($novo)['secoes'];
        $this->assertSame([['Primeira', 1], ['Segunda', 2]], array_map(static fn (array $s): array => [$s['texto'], $s['indice']], $secoes));
        $this->assertSame(['secao', 'secao', 'paragrafo'], $this->tipos($novo));
    }

    public function test_recusa_a_troca_que_transformaria_um_paragrafo_em_legenda(): void
    {
        // "Exemplo: ..." logo acima de uma figura é lido como legenda dela: o
        // parágrafo sumiria como trecho. Melhor não mover que mudar o texto.
        $c = "Exemplo: veja o fluxo abaixo.\n\nOutro parágrafo.\n\n![fluxo](/uploads/f.png)";

        $this->assertSame($c, EdicaoDaAula::mover($c, 0, 1));
    }
}
