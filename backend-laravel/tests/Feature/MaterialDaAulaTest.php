<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ConteudoDaAula;
use App\Support\MaterialDaAula;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * As operações do editor de material sobre o texto da aula.
 *
 * O que prendem: que editar UM bloco não toca no resto do texto (indentação do
 * código, espaçamento), que o que o editor recusa é o que na releitura mudaria o
 * texto sem ninguém pedir, e que a conversão entre tipos faz o que o painel
 * antigo fazia.
 */
final class MaterialDaAulaTest extends TestCase
{
    private const AULA = "## Primeira\n\nUm parágrafo.\n\n- um\n- dois\n\nCódigo 1: exemplo\n```java\nint a = 1;\n    int b = 2;\n```\n\nFigura 1 - o diagrama\n![Diagrama](/uploads/d.png)\n\n### Fim";

    /** @return list<string> */
    private function tipos(string $c): array
    {
        return array_column(ConteudoDaAula::analisar($c)['blocos'], 'tipo');
    }

    private function recusa(callable $acao, string $trecho): void
    {
        try {
            $acao();
            $this->fail('Aceitou o que devia recusar: '.$trecho);
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($trecho, $e->getMessage());
        }
    }

    // ---- mover -----------------------------------------------------------------

    public function test_mover_troca_com_o_vizinho_levando_a_legenda(): void
    {
        $novo = MaterialDaAula::mover(self::AULA, 4, 'up');

        $this->assertSame(['secao', 'paragrafo', 'listaMarcadores', 'imagem', 'codigo', 'subsecao'], $this->tipos($novo));
        $imagem = ConteudoDaAula::analisar($novo)['blocos'][3];
        $this->assertSame('Figura 1 - o diagrama', $imagem['legenda']);
    }

    public function test_mover_nas_pontas_nao_faz_nada(): void
    {
        $this->assertSame(self::AULA, MaterialDaAula::mover(self::AULA, 0, 'up'));
        $this->assertSame(self::AULA, MaterialDaAula::mover(self::AULA, 5, 'down'));
    }

    public function test_mover_recusa_a_troca_que_viraria_legenda(): void
    {
        $c = "Exemplo: veja o fluxo abaixo.\n\nOutro parágrafo.\n\n![fluxo](/uploads/f.png)";

        $this->recusa(static fn () => MaterialDaAula::mover($c, 0, 'down'), 'sem mudar o texto');
    }

    public function test_mover_trecho_inexistente_pede_para_recarregar(): void
    {
        $this->recusa(static fn () => MaterialDaAula::mover(self::AULA, 99, 'up'), 'Recarregue');
    }

    // ---- remover ---------------------------------------------------------------

    public function test_remover_leva_a_legenda_e_nao_deixa_buraco(): void
    {
        $novo = MaterialDaAula::remover(self::AULA, 4);

        $this->assertStringNotContainsString('Figura 1', $novo);
        $this->assertStringNotContainsString('/uploads/d.png', $novo);
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $novo);
        $this->assertSame(['secao', 'paragrafo', 'listaMarcadores', 'codigo', 'subsecao'], $this->tipos($novo));
    }

    public function test_remover_so_mexe_no_trecho_do_bloco(): void
    {
        $novo = MaterialDaAula::remover(self::AULA, 1);

        $this->assertStringContainsString("    int b = 2;\n", $novo, 'a indentação do código é dele');
        $this->assertStringNotContainsString('Um parágrafo.', $novo);
    }

    // ---- inserir ----------------------------------------------------------------

    public function test_inserir_modelo_depois_do_bloco(): void
    {
        $novo = MaterialDaAula::inserirModelo(self::AULA, 1, 'subsecao');

        $this->assertSame(['secao', 'paragrafo', 'subsecao', 'listaMarcadores', 'codigo', 'imagem', 'subsecao'], $this->tipos($novo));
        $this->assertSame('Subtítulo', ConteudoDaAula::analisar($novo)['blocos'][2]['texto']);
    }

    public function test_inserir_no_comeco_com_menos_um(): void
    {
        $novo = MaterialDaAula::inserirModelo(self::AULA, -1, 'paragrafo');

        $this->assertSame('paragrafo', $this->tipos($novo)[0]);
        $this->assertStringStartsWith("Escreva o parágrafo aqui.\n\n## Primeira", $novo);
    }

    public function test_inserir_em_aula_vazia(): void
    {
        $this->assertSame('## Título da seção', MaterialDaAula::inserirModelo('', -1, 'secao'));
    }

    public function test_inserir_fora_da_faixa_recusa_em_vez_de_cair_no_fim(): void
    {
        $this->recusa(static fn () => MaterialDaAula::inserirModelo(self::AULA, 99, 'paragrafo'), 'Recarregue');
        $this->recusa(static fn () => MaterialDaAula::inserirModelo(self::AULA, -2, 'paragrafo'), 'Posição inválida');
    }

    public function test_codigo_nao_e_oferecido_como_novo_e_tipo_inventado_e_recusado(): void
    {
        $this->assertNotContains('codigo', MaterialDaAula::TIPOS_NOVOS);
        $this->recusa(static fn () => MaterialDaAula::inserirModelo(self::AULA, 0, 'codigo'), 'Escolha o tipo');
        $this->recusa(static fn () => MaterialDaAula::inserirModelo(self::AULA, 0, 'imagem'), 'Escolha o tipo');
    }

    // ---- atualizar texto ---------------------------------------------------------

    public function test_atualizar_troca_so_o_trecho_do_bloco(): void
    {
        $novo = MaterialDaAula::atualizar(self::AULA, 1, ['tipo' => 'paragrafo', 'texto' => 'Texto trocado.']);

        $this->assertSame(
            str_replace('Um parágrafo.', 'Texto trocado.', self::AULA),
            $novo,
            'o resto da aula fica idêntico byte a byte',
        );
    }

    public function test_paragrafo_vira_secao_juntando_as_linhas(): void
    {
        $novo = MaterialDaAula::atualizar('Texto antigo', 0, ['tipo' => 'secao', 'texto' => "Primeira linha\nSegunda linha"]);

        $this->assertSame('## Primeira linha Segunda linha', $novo);
    }

    public function test_lista_vira_secao_e_secao_vira_lista(): void
    {
        $this->assertSame('## Abertura', MaterialDaAula::atualizar('1. Abertura', 0, ['tipo' => 'secao', 'texto' => 'Abertura']));
        $this->assertSame("1. a\n2. b", MaterialDaAula::atualizar('## x', 0, ['tipo' => 'listaNumerada', 'texto' => "a\n\n  b  \n"]));
        $this->assertSame("- a\n- b", MaterialDaAula::atualizar('## x', 0, ['tipo' => 'listaMarcadores', 'texto' => "a\nb"]));
    }

    public function test_formatacao_so_vai_para_quem_a_aceita(): void
    {
        $this->assertSame('{centro grande} Oi', MaterialDaAula::atualizar('Oi', 0, ['tipo' => 'paragrafo', 'texto' => 'Oi', 'tamanho' => 'grande', 'alinhamento' => 'centro']));
        // Subtítulo: só o alinhamento. Seção e lista descartam os dois.
        $this->assertSame('### {direita} Oi', MaterialDaAula::atualizar('Oi', 0, ['tipo' => 'subsecao', 'texto' => 'Oi', 'tamanho' => 'grande', 'alinhamento' => 'direita']));
        $this->assertSame('## Oi', MaterialDaAula::atualizar('Oi', 0, ['tipo' => 'secao', 'texto' => 'Oi', 'tamanho' => 'grande', 'alinhamento' => 'direita']));
        // O padrão não é gravado.
        $this->assertSame('Oi', MaterialDaAula::atualizar('{centro} Oi', 0, ['tipo' => 'paragrafo', 'texto' => 'Oi', 'tamanho' => 'normal', 'alinhamento' => 'esquerda']));
    }

    public function test_texto_vazio_e_formato_invalido_sao_recusados(): void
    {
        $this->recusa(static fn () => MaterialDaAula::atualizar('Oi', 0, ['tipo' => 'paragrafo', 'texto' => " \n  "]), 'Escreva algo');
        $this->recusa(static fn () => MaterialDaAula::atualizar('Oi', 0, ['tipo' => 'paragrafo', 'texto' => 'Oi', 'tamanho' => 'gigante']), 'inválido');
        $this->recusa(static fn () => MaterialDaAula::atualizar('Oi', 0, ['tipo' => 'paragrafo', 'texto' => 'Oi', 'alinhamento' => 'diagonal']), 'inválido');
        $this->recusa(static fn () => MaterialDaAula::atualizar('Oi', 0, ['tipo' => 'codigo', 'texto' => 'Oi']), 'Escolha o tipo');
    }

    // ---- atualizar código --------------------------------------------------------

    public function test_codigo_preserva_a_indentacao_e_aceita_legenda_no_padrao(): void
    {
        $novo = MaterialDaAula::atualizar(self::AULA, 3, [
            'linguagem' => 'php', 'legenda' => 'Código 2:  novo   exemplo', 'codigo' => "<?php\n    echo 1;\n",
        ]);

        $this->assertStringContainsString("Código 2: novo exemplo\n```php\n<?php\n    echo 1;\n\n```", $novo);
        $bloco = ConteudoDaAula::analisar($novo)['blocos'][3];
        $this->assertSame('codigo', $bloco['tipo']);
        $this->assertSame("<?php\n    echo 1;", $bloco['codigo']);
        $this->assertSame('Código 2: novo exemplo', $bloco['legenda']);
    }

    public function test_codigo_recusa_legenda_fora_do_padrao_cerca_solta_e_linguagem_desconhecida(): void
    {
        $this->recusa(static fn () => MaterialDaAula::atualizar(self::AULA, 3, ['linguagem' => 'java', 'legenda' => 'qualquer coisa', 'codigo' => 'x']), 'Comece a legenda');
        $this->recusa(static fn () => MaterialDaAula::atualizar(self::AULA, 3, ['linguagem' => 'java', 'codigo' => "a\n```\nb"]), 'três crases');
        $this->recusa(static fn () => MaterialDaAula::atualizar(self::AULA, 3, ['linguagem' => 'cobol', 'codigo' => 'x']), 'linguagens');
    }

    public function test_codigo_mantem_a_linguagem_que_ja_tinha_mesmo_fora_da_lista(): void
    {
        $c = "```rust\nfn main() {}\n```";

        $novo = MaterialDaAula::atualizar($c, 0, ['linguagem' => 'rust', 'codigo' => 'fn main() { }']);

        $this->assertStringStartsWith('```rust', $novo);
    }

    // ---- mídia -------------------------------------------------------------------

    public function test_atualizar_imagem_e_video(): void
    {
        $imagem = MaterialDaAula::atualizar(self::AULA, 4, ['url' => '/uploads/novo.png', 'descricao' => 'Novo   diagrama', 'legenda' => 'Figura 2 - novo']);
        $this->assertStringContainsString("Figura 2 - novo\n![Novo diagrama](/uploads/novo.png)", $imagem);
        $this->assertStringNotContainsString('Figura 1', $imagem);

        $video = MaterialDaAula::atualizar('@[Velho](https://youtu.be/dQw4w9WgXcQ)', 0, ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'descricao' => 'Demonstração']);
        $this->assertSame('@[Demonstração](https://youtu.be/dQw4w9WgXcQ)', $video);
    }

    public function test_midia_recusa_o_que_o_painel_antigo_recusava(): void
    {
        $this->recusa(static fn () => MaterialDaAula::midiaNova('imagem', ['url' => '', 'descricao' => 'x']), 'Escolha a imagem');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('imagem', ['url' => '/uploads/a.png', 'descricao' => '  ']), 'Descreva a imagem');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('imagem', ['url' => '/uploads/a b.png', 'descricao' => 'x']), 'espaço nem parênteses');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('imagem', ['url' => '/uploads/a(1).png', 'descricao' => 'x']), 'espaço nem parênteses');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('imagem', ['url' => 'javascript:alert(1)', 'descricao' => 'x']), 'espaço nem parênteses');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('imagem', ['url' => 'javascript:alert1', 'descricao' => 'x']), 'não é válido');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('video', ['url' => 'https://exemplo.com/x', 'descricao' => 'x']), 'Não reconhecemos');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('video', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'descricao' => '']), 'título ao vídeo');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('imagem', ['url' => '/uploads/a.png', 'descricao' => 'x', 'legenda' => 'solta']), 'Comece a legenda');
        $this->recusa(static fn () => MaterialDaAula::midiaNova('audio', ['url' => 'x', 'descricao' => 'x']), 'Escolha o tipo');
    }

    public function test_midia_nova_entra_pronta_para_inserir(): void
    {
        $trecho = MaterialDaAula::midiaNova('imagem', ['url' => '/uploads/a.png', 'descricao' => 'Mapa [antigo]', 'legenda' => 'Figura 3: mapa']);

        $this->assertSame("Figura 3: mapa\n![Mapa antigo](/uploads/a.png)", $trecho);
        $novo = MaterialDaAula::inserir(self::AULA, 0, $trecho);
        $this->assertSame('imagem', ConteudoDaAula::analisar($novo)['blocos'][1]['tipo']);
    }

    // ---- invariantes -----------------------------------------------------------------

    /** Nenhuma operação muda o que o aluno vê além do que foi pedido. */
    public function test_toda_operacao_deixa_a_aula_relida_com_os_blocos_esperados(): void
    {
        $antes = $this->tipos(self::AULA);

        $this->assertSame(count($antes) + 1, count($this->tipos(MaterialDaAula::inserirModelo(self::AULA, 2, 'listaNumerada'))));
        $this->assertSame(count($antes) - 1, count($this->tipos(MaterialDaAula::remover(self::AULA, 2))));
        $this->assertSame(count($antes), count($this->tipos(MaterialDaAula::mover(self::AULA, 2, 'down'))));
        $this->assertSame(count($antes), count($this->tipos(MaterialDaAula::atualizar(self::AULA, 0, ['tipo' => 'secao', 'texto' => 'Outra']))));
    }
}
