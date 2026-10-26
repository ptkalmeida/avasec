<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ConteudoDaAula;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Leitura do conteúdo de aula, em PHP.
 *
 * Os casos aqui são os mesmos de `tests/frontend/lessonContent.test.ts`, caso a
 * caso. Não é duplicação por desleixo: enquanto a aula puder ser lida pelas
 * duas implementações, elas têm de concordar — e o que está em jogo é o
 * conteúdo didático, a única parte do sistema que o aluno de fato vem ler.
 *
 * O teste do TypeScript continua valendo e continua rodando. Nenhum dos dois
 * substitui o outro enquanto as duas leituras existirem.
 *
 * Fora de escopo, de propósito: serializar, substituir e mover bloco — isso é
 * do editor do instrutor (fase M6) e não foi portado.
 */
final class ConteudoDaAulaTest extends TestCase
{
    public function test_conteudo_vazio_nao_quebra(): void
    {
        $r = ConteudoDaAula::analisar('');

        $this->assertSame([], $r['blocos']);
        $this->assertSame([], $r['secoes']);
        $this->assertFalse($r['temCodigo']);
    }

    public function test_nulo_e_tratado_como_vazio(): void
    {
        // Aula recém-criada tem `content` nulo no banco; ler isso não pode ser erro.
        $this->assertSame([], ConteudoDaAula::analisar(null)['blocos']);
    }

    public function test_numera_secoes_e_mantem_subtitulos_sem_numero(): void
    {
        $r = ConteudoDaAula::analisar("## Primeira\n\n### Detalhe\n\n## Segunda");

        $this->assertSame([
            ['id' => 'primeira', 'texto' => 'Primeira', 'nivel' => 2, 'indice' => 1],
            ['id' => 'detalhe', 'texto' => 'Detalhe', 'nivel' => 3],
            ['id' => 'segunda', 'texto' => 'Segunda', 'nivel' => 2, 'indice' => 2],
        ], $r['secoes']);
    }

    /** A âncora do índice é o ponto onde a falta do `intl` poderia divergir. */
    public function test_gera_ancora_sem_acento(): void
    {
        $this->assertSame('heranca-e-instanciacao', ConteudoDaAula::analisar('## Herança e Instanciação')['secoes'][0]['id']);
        $this->assertSame('o-que-e-ux-design', ConteudoDaAula::analisar('### O que é UX Design?')['secoes'][0]['id']);
        $this->assertSame('referencias', ConteudoDaAula::analisar('### Referências')['secoes'][0]['id']);
        // `&` e os espaços ao redor colapsam num traço só — conferido contra a
        // implementação em TypeScript, não suposto.
        $this->assertSame('acao-reacao', ConteudoDaAula::analisar('## Ação & Reação')['secoes'][0]['id']);
    }

    /** Título só com pontuação geraria âncora vazia, e âncora vazia não ancora. */
    public function test_titulo_sem_letra_cai_no_radical_numerado(): void
    {
        $r = ConteudoDaAula::analisar("## ---\n## ???");

        $this->assertSame('secao-0', $r['secoes'][0]['id']);
        $this->assertSame('secao-1', $r['secoes'][1]['id']);
    }

    public function test_conteudo_antigo_so_com_subtitulo_continua_renderizando(): void
    {
        $r = ConteudoDaAula::analisar("### O que é UX Design?\nUX trata da experiência.");

        $this->assertSame('subsecao', $r['blocos'][0]['tipo']);
        $this->assertSame('o-que-e-ux-design', $r['blocos'][0]['id']);
        $this->assertSame('paragrafo', $r['blocos'][1]['tipo']);
        $this->assertSame('UX trata da experiência.', $r['blocos'][1]['texto']);
    }

    // ---------- bloco de código ----------

    public function test_extrai_codigo_com_linguagem_e_preserva_a_indentacao(): void
    {
        $r = ConteudoDaAula::analisar("```java\npublic class Pessoa {\n    private String nome;\n}\n```");

        $this->assertTrue($r['temCodigo']);
        $this->assertCount(1, $r['blocos']);
        $this->assertSame('java', $r['blocos'][0]['linguagem']);
        $this->assertNull($r['blocos'][0]['legenda']);
        $this->assertSame("public class Pessoa {\n    private String nome;\n}", $r['blocos'][0]['codigo']);
    }

    public function test_aceita_codigo_sem_linguagem(): void
    {
        $this->assertNull(ConteudoDaAula::analisar("```\nsem realce\n```")['blocos'][0]['linguagem']);
    }

    public function test_fecha_o_codigo_mesmo_sem_cerca_final(): void
    {
        $r = ConteudoDaAula::analisar("```java\nint a = 1;");

        $this->assertSame('codigo', $r['blocos'][0]['tipo']);
        $this->assertSame('int a = 1;', $r['blocos'][0]['codigo']);
    }

    public function test_imagem_dentro_do_codigo_continua_codigo(): void
    {
        $r = ConteudoDaAula::analisar("```md\n![Foto](/uploads/x.png)\n```");

        $this->assertCount(1, $r['blocos']);
        $this->assertSame('codigo', $r['blocos'][0]['tipo']);
        $this->assertSame('![Foto](/uploads/x.png)', $r['blocos'][0]['codigo']);
    }

    // ---------- legendas ----------

    public function test_associa_a_legenda_ao_codigo_seguinte(): void
    {
        $r = ConteudoDaAula::analisar("Código 5: Método atualizarID:\n```java\nvoid x() {}\n```");

        $this->assertCount(1, $r['blocos']);
        $this->assertSame('Código 5: Método atualizarID:', $r['blocos'][0]['legenda']);
    }

    public function test_associa_a_legenda_a_imagem_seguinte(): void
    {
        $r = ConteudoDaAula::analisar("Figura 1 - o diagrama\n![Diagrama](/uploads/x.png)");

        $this->assertCount(1, $r['blocos']);
        $this->assertSame('imagem', $r['blocos'][0]['tipo']);
        $this->assertSame('Figura 1 - o diagrama', $r['blocos'][0]['legenda']);
    }

    public function test_legenda_sem_midia_depois_vira_paragrafo(): void
    {
        $r = ConteudoDaAula::analisar("Código 5: isso nunca virou bloco.\n\n## Fim");

        $this->assertSame('paragrafo', $r['blocos'][0]['tipo']);
        $this->assertSame('Código 5: isso nunca virou bloco.', $r['blocos'][0]['texto']);
    }

    public function test_reconhece_legenda_e_recusa_texto_solto(): void
    {
        $this->assertTrue(ConteudoDaAula::ehLegenda('Figura 2: o fluxo'));
        $this->assertTrue(ConteudoDaAula::ehLegenda('Código 1 - exemplo'));
        $this->assertFalse(ConteudoDaAula::ehLegenda('Uma frase qualquer sobre figuras'));
    }

    // ---------- listas ----------

    public function test_separa_lista_numerada_de_lista_com_marcador(): void
    {
        $r = ConteudoDaAula::analisar("1. um\n2. dois\n\n- alfa\n- beta");

        $this->assertCount(2, $r['blocos']);
        $this->assertSame('listaNumerada', $r['blocos'][0]['tipo']);
        $this->assertSame(['um', 'dois'], $r['blocos'][0]['itens']);
        $this->assertSame('listaMarcadores', $r['blocos'][1]['tipo']);
        $this->assertSame(['alfa', 'beta'], $r['blocos'][1]['itens']);
    }

    /** Trocar de tipo sem linha em branco no meio também separa as listas. */
    public function test_troca_de_tipo_fecha_a_lista_anterior(): void
    {
        $r = ConteudoDaAula::analisar("1. um\n- alfa");

        $this->assertCount(2, $r['blocos']);
        $this->assertSame('listaNumerada', $r['blocos'][0]['tipo']);
        $this->assertSame('listaMarcadores', $r['blocos'][1]['tipo']);
    }

    // ---------- mídia ----------

    public function test_reconhece_imagem_sozinha_na_linha(): void
    {
        $bloco = ConteudoDaAula::analisar('![Diagrama das classes](/uploads/x.png)')['blocos'][0];

        $this->assertSame('imagem', $bloco['tipo']);
        $this->assertSame('/uploads/x.png', $bloco['url']);
        $this->assertSame('Diagrama das classes', $bloco['alt']);
        $this->assertNull($bloco['legenda']);
    }

    public function test_reconhece_video_sem_colidir_com_imagem(): void
    {
        $r = ConteudoDaAula::analisar("@[Demonstração](https://youtu.be/abc123)\n\n![Foto](/uploads/f.png)");

        $this->assertSame('video', $r['blocos'][0]['tipo']);
        $this->assertSame('https://youtu.be/abc123', $r['blocos'][0]['url']);
        $this->assertSame('Demonstração', $r['blocos'][0]['titulo']);
        $this->assertSame('imagem', $r['blocos'][1]['tipo']);
    }

    public function test_imagem_sem_descricao_tem_alt_vazio(): void
    {
        // Descuido de autoria não pode virar marcação crua na tela do aluno.
        $this->assertSame('', ConteudoDaAula::analisar('![](/uploads/x.png)')['blocos'][0]['alt']);
    }

    /**
     * @param  string  $linha  entrada que NÃO pode virar bloco de mídia
     */
    #[DataProvider('linhasQueNaoSaoMidia')]
    public function test_linha_que_nao_e_midia_vira_paragrafo(string $caso, string $linha): void
    {
        $this->assertSame(
            'paragrafo',
            ConteudoDaAula::analisar($linha)['blocos'][0]['tipo'],
            "Deveria ser parágrafo: {$caso}",
        );
    }

    /** @return array<string, array{string, string}> */
    public static function linhasQueNaoSaoMidia(): array
    {
        return [
            'sem endereço' => ['sem endereço', '![Descrição]()'],
            'texto além da imagem' => ['texto além da imagem', 'Veja ![Foto](/uploads/x.png) aqui'],
            'colchete na descrição' => ['colchete na descrição', '![Figura [1] do livro](/uploads/x.png)'],
        ];
    }

    // ---------- formatação do autor ----------

    public function test_le_alinhamento_e_tamanho_e_tira_o_marcador_do_texto(): void
    {
        $bloco = ConteudoDaAula::analisar('{centro grande} Texto de abertura.')['blocos'][0];

        $this->assertSame('paragrafo', $bloco['tipo']);
        $this->assertSame('Texto de abertura.', $bloco['texto']);
        $this->assertSame('centro', $bloco['alinhamento']);
        $this->assertSame('grande', $bloco['tamanho']);
    }

    public function test_aceita_as_palavras_em_qualquer_ordem(): void
    {
        $bloco = ConteudoDaAula::analisar('{muito-grande justificado} Texto.')['blocos'][0];

        $this->assertSame('muito-grande', $bloco['tamanho']);
        $this->assertSame('justificado', $bloco['alinhamento']);
    }

    public function test_paragrafo_sem_marcador_nao_carrega_formatacao(): void
    {
        $bloco = ConteudoDaAula::analisar('Texto comum.')['blocos'][0];

        $this->assertArrayNotHasKey('tamanho', $bloco);
        $this->assertArrayNotHasKey('alinhamento', $bloco);
    }

    /** O valor padrão não é gravado: lido, some, para o bloco ter uma forma só. */
    public function test_o_padrao_nao_vira_formatacao(): void
    {
        $bloco = ConteudoDaAula::analisar('{esquerda normal} Texto.')['blocos'][0];

        $this->assertSame('Texto.', $bloco['texto']);
        $this->assertArrayNotHasKey('tamanho', $bloco);
        $this->assertArrayNotHasKey('alinhamento', $bloco);
    }

    #[DataProvider('marcadoresInvalidos')]
    public function test_marcador_invalido_fica_como_texto_sem_sumir(string $caso, string $linha): void
    {
        $bloco = ConteudoDaAula::analisar($linha)['blocos'][0];

        $this->assertSame('paragrafo', $bloco['tipo'], $caso);
        $this->assertSame($linha, $bloco['texto'], "O texto do autor sumiu: {$caso}");
        $this->assertArrayNotHasKey('tamanho', $bloco);
        $this->assertArrayNotHasKey('alinhamento', $bloco);
    }

    /** @return array<string, array{string, string}> */
    public static function marcadoresInvalidos(): array
    {
        return [
            'palavra desconhecida' => ['palavra desconhecida', '{negrito} Texto.'],
            'dois tamanhos' => ['dois tamanhos', '{grande pequeno} Texto.'],
            'dois alinhamentos' => ['dois alinhamentos', '{centro direita} Texto.'],
            'marcador vazio' => ['marcador vazio', '{} Texto.'],
            'sem espaço antes do texto' => ['sem espaço antes do texto', '{centro}Texto.'],
            'estilo livre' => ['estilo livre', '{color:red} Texto.'],
        ];
    }

    public function test_subtitulo_aceita_so_alinhamento(): void
    {
        $r = ConteudoDaAula::analisar('### {direita} Referências');

        $this->assertSame('subsecao', $r['blocos'][0]['tipo']);
        $this->assertSame('Referências', $r['blocos'][0]['texto']);
        $this->assertSame('direita', $r['blocos'][0]['alinhamento']);
        $this->assertSame([['id' => 'referencias', 'texto' => 'Referências', 'nivel' => 3]], $r['secoes']);
    }

    public function test_tamanho_no_subtitulo_invalida_o_marcador(): void
    {
        // A hierarquia da aula não é do autor: o tamanho é o que separa o
        // subtítulo do parágrafo.
        $bloco = ConteudoDaAula::analisar('### {grande} Referências')['blocos'][0];

        $this->assertSame('subsecao', $bloco['tipo']);
        $this->assertSame('{grande} Referências', $bloco['texto']);
    }

    public function test_linha_formatada_e_paragrafo_nunca_legenda(): void
    {
        $r = ConteudoDaAula::analisar("{centro} Figura 1: fluxo\n![fluxo](/uploads/f.png)");

        $this->assertSame('paragrafo', $r['blocos'][0]['tipo']);
        $this->assertSame('Figura 1: fluxo', $r['blocos'][0]['texto']);
        $this->assertSame('centro', $r['blocos'][0]['alinhamento']);
        $this->assertSame('imagem', $r['blocos'][1]['tipo']);
        $this->assertNull($r['blocos'][1]['legenda']);
    }

    // ---------- uma aula inteira ----------

    public function test_le_uma_aula_com_todos_os_tipos_de_bloco(): void
    {
        $conteudo = implode("\n", [
            '## Primeira seção',
            '',
            'Um parágrafo qualquer.',
            '',
            '1. um',
            '2. dois',
            '',
            'Código 1: exemplo:',
            '```java',
            'int a = 1;',
            '```',
            '',
            'Figura 1 - o diagrama',
            '![Diagrama das classes](/uploads/diagrama.png)',
            '',
            '@[Demonstração](https://youtu.be/abc123)',
            '',
            '### Fim',
        ]);

        $tipos = array_column(ConteudoDaAula::analisar($conteudo)['blocos'], 'tipo');

        $this->assertSame(
            ['secao', 'paragrafo', 'listaNumerada', 'codigo', 'imagem', 'video', 'subsecao'],
            $tipos,
        );
    }
}
