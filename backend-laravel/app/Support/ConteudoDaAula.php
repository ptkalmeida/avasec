<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Leitura do conteúdo de aula — a tradução PHP de `src/utils/lessonContent.ts`.
 *
 * O instrutor escreve num Markdown reduzido, e este é o único lugar que sabe
 * lê-lo:
 *
 *     ## Seção              seção principal (entra no índice, numerada)
 *     ### Subtítulo         subtítulo (entra no índice, sem número)
 *     **negrito**           negrito (aplicado na renderização, não aqui)
 *     1. item               lista numerada
 *     - item                lista com marcador
 *     ```java               bloco de código (a linguagem é opcional)
 *     ![descrição](/x.png)  imagem (sozinha na linha)
 *
 *     @[título](https://…)  vídeo embutido (sozinho na linha)
 *     Figura 1: algo        legenda, quando imediatamente antes de mídia ou código
 *     {centro grande} texto parágrafo com alinhamento e/ou tamanho
 *
 * ## Só lê
 *
 * Esta classe **lê**. Serializar, substituir, inserir e mover bloco é de
 * `EdicaoDaAula`, que parte dos trechos de `analisarComTrechos`: cada bloco
 * carrega a posição do texto que o gerou, para o editor trocar UM bloco sem
 * reserializar o resto. `analisar` devolve a forma de sempre, sem trechos —
 * quem lê a aula para mostrá-la ao aluno não os usa.
 *
 * ## Nada daqui vira HTML
 *
 * A saída é estrutura de dados; quem monta a marcação é o Blade, escapando cada
 * texto. Essa é a razão de o formato do autor ser uma lista FECHADA de opções
 * (tamanho e alinhamento) em vez de estilo livre: conteúdo autorado nunca
 * injeta marcação.
 *
 * `ConteudoDaAulaTest` repete os casos do Vitest (`tests/frontend/lessonContent.test.ts`).
 * Enquanto as duas implementações existirem, elas precisam concordar — é o
 * conteúdo didático que está em jogo.
 */
final class ConteudoDaAula
{
    /** Tamanhos que o autor pode pedir num parágrafo. */
    public const TAMANHOS = ['pequeno', 'normal', 'grande', 'muito-grande'];

    /** Alinhamentos que o autor pode pedir. */
    public const ALINHAMENTOS = ['esquerda', 'centro', 'direita', 'justificado'];

    private const TAMANHO_PADRAO = 'normal';

    private const ALINHAMENTO_PADRAO = 'esquerda';

    /** Imagem e vídeo sozinhos na linha. O primeiro caractere separa as famílias. */
    private const IMAGEM = '/^!\[([^\]]*)\]\(([^()\s]+)\)$/u';

    private const VIDEO = '/^@\[([^\]]*)\]\(([^()\s]+)\)$/u';

    /** `{centro grande} texto`: marcador no início, separado do texto por espaço. */
    private const FORMATO = '/^\{([a-z -]+)\}\s+(\S.*)$/u';

    /** "Código 3: descrição" / "Figura 2 - descrição" e parentes. */
    private const LEGENDA = '/^(?:c[óo]digo|figura|imagem|gr[áa]fico|v[íi]deo|tabela|quadro|exemplo)\s*\d*\s*[:.–-]\s*.+/iu';

    /**
     * @return array{blocos: list<array<string, mixed>>, secoes: list<array<string, mixed>>, temCodigo: bool}
     */
    public static function analisar(?string $conteudo): array
    {
        $r = self::ler($conteudo);

        // Quem lê a aula não usa o trecho; sai da forma pública para ela ser a
        // mesma de sempre.
        foreach ($r['blocos'] as &$bloco) {
            unset($bloco['inicio'], $bloco['fim']);
        }
        unset($bloco);

        return $r;
    }

    /**
     * O mesmo que `analisar`, e cada bloco com o TRECHO do texto que o gerou:
     * `inicio` e `fim` são posições em BYTES, com `fim` exclusivo.
     *
     * É o que permite ao editor trocar UM bloco por emenda no texto, deixando o
     * resto da aula idêntico byte a byte, sem passar por reserialização. Byte e
     * não caractere porque a posição só circula dentro do PHP e as bordas são
     * sempre quebras de linha, que em UTF-8 nunca caem no meio de um caractere.
     *
     * @return array{blocos: list<array<string, mixed>>, secoes: list<array<string, mixed>>, temCodigo: bool}
     */
    public static function analisarComTrechos(?string $conteudo): array
    {
        return self::ler($conteudo);
    }

    /**
     * @return array{blocos: list<array<string, mixed>>, secoes: list<array<string, mixed>>, temCodigo: bool}
     */
    private static function ler(?string $conteudo): array
    {
        return (new self(explode("\n", $conteudo ?? '')))->executar();
    }

    /** @param  list<string>  $linhas */
    private function __construct(private readonly array $linhas)
    {
        // Onde cada linha começa no texto original, para montar os trechos.
        $cursor = 0;
        foreach ($linhas as $linha) {
            $this->comeco[] = $cursor;
            $cursor += strlen($linha) + 1; // +1 = o "\n" consumido pelo explode
        }
    }

    /** @var list<int> */
    private array $comeco = [];

    /** @var list<array<string, mixed>> */
    private array $blocos = [];

    /** @var list<string> */
    private array $numeradas = [];

    /** @var list<string> */
    private array $marcadores = [];

    private int $inicioLista = 0;

    private int $fimLista = 0;

    /** Última legenda vista, à espera de uma mídia ou de um bloco de código. */
    private ?string $legendaPendente = null;

    private int $linhaDaLegenda = 0;

    /**
     * @return array{blocos: list<array<string, mixed>>, secoes: list<array<string, mixed>>, temCodigo: bool}
     */
    private function executar(): array
    {
        $total = count($this->linhas);
        $secoes = [];
        $temCodigo = false;
        $contaSecoes = 0;
        $contaAncoras = 0;

        for ($i = 0; $i < $total; $i++) {
            $linha = trim($this->linhas[$i]);

            // ---- bloco de código: consome até a cerca de fechamento ----
            if (preg_match('/^```\s*([A-Za-z0-9+#-]*)\s*$/u', $linha, $cerca) === 1) {
                $this->despejarListas();

                // A legenda faz parte deste bloco: editar o código edita a legenda junto.
                $inicioDoBloco = $this->legendaPendente !== null ? $this->comeco[$this->linhaDaLegenda] : $this->comeco[$i];

                $corpo = [];
                $i++;
                while ($i < $total && trim($this->linhas[$i]) !== '```') {
                    $corpo[] = $this->linhas[$i];
                    $i++;
                }

                // Tira as linhas vazias das pontas SEM tocar na indentação do código.
                while ($corpo !== [] && trim($corpo[0]) === '') {
                    array_shift($corpo);
                }
                while ($corpo !== [] && trim($corpo[count($corpo) - 1]) === '') {
                    array_pop($corpo);
                }

                $this->blocos[] = [
                    'tipo' => 'codigo',
                    'linguagem' => $cerca[1] === '' ? null : mb_strtolower($cerca[1]),
                    'legenda' => $this->pegarLegenda(),
                    'codigo' => implode("\n", $corpo),
                    'inicio' => $inicioDoBloco,
                    // $i aponta para a cerca final (ou para o fim do texto, se ela faltar).
                    'fim' => $this->fimDe(min($i, $total - 1)),
                ];
                $temCodigo = true;

                continue;
            }

            if ($linha === '') {
                $this->despejarListas();

                continue;
            }

            $ehImagem = preg_match(self::IMAGEM, $linha, $imagem) === 1;
            $ehVideo = preg_match(self::VIDEO, $linha, $video) === 1;
            $ehSecao = preg_match('/^##\s+(?!#)(.*)$/u', $linha, $secao) === 1;
            $ehSubsecao = preg_match('/^###\s+(.*)$/u', $linha, $subsecao) === 1;
            $ehNumerada = preg_match('/^\d+\.\s+(.*)$/u', $linha, $numerada) === 1;
            $ehMarcador = preg_match('/^[-*]\s+(.*)$/u', $linha, $marcador) === 1;

            $trecho = ['inicio' => $this->comeco[$i], 'fim' => $this->fimDe($i)];
            // A legenda pendente pertence à mídia: o trecho engloba as duas linhas,
            // então editar ou remover a figura leva a legenda junto.
            $trechoComLegenda = $this->legendaPendente !== null
                ? ['inicio' => $this->comeco[$this->linhaDaLegenda], 'fim' => $this->fimDe($i)]
                : $trecho;

            if ($ehImagem) {
                $this->despejarListas();
                $this->blocos[] = [
                    'tipo' => 'imagem',
                    'url' => $imagem[2],
                    // `alt` é string, nunca null: o atributo é obrigatório no HTML
                    // e '' tem sentido próprio ("imagem decorativa").
                    'alt' => $imagem[1],
                    'legenda' => $this->pegarLegenda(),
                ] + $trechoComLegenda;
            } elseif ($ehVideo) {
                $this->despejarListas();
                $this->blocos[] = [
                    'tipo' => 'video',
                    'url' => $video[2],
                    'titulo' => $video[1],
                    'legenda' => $this->pegarLegenda(),
                ] + $trechoComLegenda;
            } elseif ($ehSecao) {
                $this->despejarListas();
                $this->despejarLegenda();
                $contaSecoes++;
                $id = self::ancora($secao[1], $contaAncoras++);
                $this->blocos[] = ['tipo' => 'secao', 'id' => $id, 'texto' => $secao[1], 'indice' => $contaSecoes] + $trecho;
                $secoes[] = ['id' => $id, 'texto' => $secao[1], 'nivel' => 2, 'indice' => $contaSecoes];
            } elseif ($ehSubsecao) {
                $this->despejarListas();
                $this->despejarLegenda();

                // Subtítulo aceita só ALINHAMENTO: o tamanho é o que o distingue
                // do parágrafo na hierarquia da aula, e essa escada não é do autor.
                $formato = self::lerFormato($subsecao[1], aceitaTamanho: false);
                $texto = $formato['texto'] ?? $subsecao[1];
                $id = self::ancora($texto, $contaAncoras++);

                $bloco = ['tipo' => 'subsecao', 'id' => $id, 'texto' => $texto];
                if (isset($formato['alinhamento'])) {
                    $bloco['alinhamento'] = $formato['alinhamento'];
                }

                $this->blocos[] = $bloco + $trecho;
                $secoes[] = ['id' => $id, 'texto' => $texto, 'nivel' => 3];
            } elseif ($ehNumerada) {
                $this->despejarLegenda();
                if ($this->marcadores !== []) {
                    $this->despejarListas();
                }
                if ($this->numeradas === []) {
                    $this->inicioLista = $i;
                }
                $this->fimLista = $i;
                $this->numeradas[] = $numerada[1];
            } elseif ($ehMarcador) {
                $this->despejarLegenda();
                if ($this->numeradas !== []) {
                    $this->despejarListas();
                }
                if ($this->marcadores === []) {
                    $this->inicioLista = $i;
                }
                $this->fimLista = $i;
                $this->marcadores[] = $marcador[1];
            } else {
                $this->despejarListas();
                $formato = self::lerFormato($linha, aceitaTamanho: true);

                if ($formato !== null) {
                    // Linha formatada é parágrafo por ESCOLHA do autor — nunca é
                    // lida como legenda da mídia seguinte.
                    $this->despejarLegenda();
                    $bloco = ['tipo' => 'paragrafo', 'texto' => $formato['texto']];
                    foreach (['tamanho', 'alinhamento'] as $chave) {
                        if (isset($formato[$chave])) {
                            $bloco[$chave] = $formato[$chave];
                        }
                    }
                    $this->blocos[] = $bloco + $trecho;
                } elseif (self::ehLegenda($linha)) {
                    $this->despejarLegenda();
                    $this->legendaPendente = $linha;
                    $this->linhaDaLegenda = $i;
                } else {
                    $this->despejarLegenda();
                    $this->blocos[] = ['tipo' => 'paragrafo', 'texto' => $linha] + $trecho;
                }
            }
        }

        $this->despejarListas();
        $this->despejarLegenda();

        return ['blocos' => $this->blocos, 'secoes' => $secoes, 'temCodigo' => $temCodigo];
    }

    /** Fim (exclusivo) da linha `$i` no texto original. */
    private function fimDe(int $i): int
    {
        return $this->comeco[$i] + strlen($this->linhas[$i]);
    }

    /**
     * Fecha a lista aberta, se houver. Chamado em toda fronteira: linha em
     * branco, troca de tipo de lista, ou qualquer bloco que não seja item.
     */
    private function despejarListas(): void
    {
        if ($this->numeradas === [] && $this->marcadores === []) {
            return;
        }

        $trecho = ['inicio' => $this->comeco[$this->inicioLista], 'fim' => $this->fimDe($this->fimLista)];

        if ($this->numeradas !== []) {
            $this->blocos[] = ['tipo' => 'listaNumerada', 'itens' => $this->numeradas] + $trecho;
            $this->numeradas = [];
        }

        if ($this->marcadores !== []) {
            $this->blocos[] = ['tipo' => 'listaMarcadores', 'itens' => $this->marcadores] + $trecho;
            $this->marcadores = [];
        }
    }

    /**
     * Legenda que não encontrou mídia nenhuma vira parágrafo comum — o autor vê o
     * texto dele na tela, e não uma linha que sumiu.
     */
    private function despejarLegenda(): void
    {
        if ($this->legendaPendente !== null) {
            $this->blocos[] = [
                'tipo' => 'paragrafo',
                'texto' => $this->legendaPendente,
                'inicio' => $this->comeco[$this->linhaDaLegenda],
                'fim' => $this->fimDe($this->linhaDaLegenda),
            ];
            $this->legendaPendente = null;
        }
    }

    /** Consumir a legenda é esvaziá-la: a mesma não cola em dois blocos. */
    private function pegarLegenda(): ?string
    {
        $valor = $this->legendaPendente;
        $this->legendaPendente = null;

        return $valor;
    }

    /** A linha serve de legenda de mídia ou de código? */
    public static function ehLegenda(string $texto): bool
    {
        return preg_match(self::LEGENDA, trim($texto)) === 1;
    }

    /**
     * Âncora estável do título, para o índice saltar até ele.
     *
     * O original faz `normalize('NFD')` e remove os acentos combinantes. Aqui
     * não há a extensão `intl`, então quem tira o acento é `Str::ascii`, cuja
     * tabela cobre o mesmo e mais um pouco. A diferença aparece em casos que
     * título de aula não tem (o alemão `ß` vira `ss` em vez de sumir), e os
     * casos reais — `Herança e Instanciação`, `Referências` — estão no teste.
     */
    private static function ancora(string $texto, int $reserva): string
    {
        $base = Str::ascii(mb_strtolower($texto));
        $base = (string) preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim($base, '-');

        return $base === '' ? 'secao-'.$reserva : $base;
    }

    /**
     * Lê o marcador de formatação do começo da linha.
     *
     * Devolve null — e a linha segue como texto comum, com as chaves à vista —
     * quando o marcador não é válido: palavra desconhecida, dois tamanhos, dois
     * alinhamentos, ou tamanho num lugar que não aceita tamanho. **Falha
     * visível é melhor que formatação adivinhada**: o autor vê o próprio texto
     * e corrige; adivinhar deixaria a aula com uma formatação que ninguém pediu.
     *
     * @return array{texto:string, tamanho?:string, alinhamento?:string}|null
     */
    private static function lerFormato(string $linha, bool $aceitaTamanho): ?array
    {
        if (preg_match(self::FORMATO, $linha, $m) !== 1) {
            return null;
        }

        $formato = [];

        foreach (array_filter(explode(' ', $m[1]), static fn (string $p): bool => $p !== '') as $palavra) {
            if (in_array($palavra, self::TAMANHOS, true) && $aceitaTamanho && ! isset($formato['tamanho'])) {
                $formato['tamanho'] = $palavra;
            } elseif (in_array($palavra, self::ALINHAMENTOS, true) && ! isset($formato['alinhamento'])) {
                $formato['alinhamento'] = $palavra;
            } else {
                return null;
            }
        }

        // O padrão não é gravado; lido, some, para o bloco ter uma forma só.
        if (($formato['tamanho'] ?? null) === self::TAMANHO_PADRAO) {
            unset($formato['tamanho']);
        }
        if (($formato['alinhamento'] ?? null) === self::ALINHAMENTO_PADRAO) {
            unset($formato['alinhamento']);
        }

        return $formato + ['texto' => $m[2]];
    }
}
