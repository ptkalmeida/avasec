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
 * ## O que esta classe NÃO faz, e por que isso importa
 *
 * Ela só **lê**. Não serializa, não substitui bloco, não move bloco — tudo isso
 * pertence ao editor do instrutor, que é a fase M6 e tem decisão técnica
 * própria. Portar agora o que ninguém chama criaria código sem consumidor, que
 * é como uma tradução diverge do original sem ninguém notar.
 *
 * Pelo mesmo motivo não há `range`. No TypeScript cada bloco carrega o trecho
 * do texto que o gerou, para o editor trocar UM bloco sem reserializar o resto.
 * Quem lê a aula não usa isso.
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
        $linhas = explode("\n", $conteudo ?? '');

        $blocos = [];
        $secoes = [];
        $temCodigo = false;

        $numeradas = [];
        $marcadores = [];
        $contaSecoes = 0;
        $contaAncoras = 0;

        /** Última legenda vista, à espera de uma mídia ou de um bloco de código. */
        $legendaPendente = null;

        $total = count($linhas);

        for ($i = 0; $i < $total; $i++) {
            $linha = trim($linhas[$i]);

            // ---- bloco de código: consome até a cerca de fechamento ----
            if (preg_match('/^```\s*([A-Za-z0-9+#-]*)\s*$/u', $linha, $cerca) === 1) {
                self::despejarListas($blocos, $numeradas, $marcadores);

                $corpo = [];
                $i++;
                while ($i < $total && trim($linhas[$i]) !== '```') {
                    $corpo[] = $linhas[$i];
                    $i++;
                }

                // Tira as linhas vazias das pontas SEM tocar na indentação do código.
                while ($corpo !== [] && trim($corpo[0]) === '') {
                    array_shift($corpo);
                }
                while ($corpo !== [] && trim($corpo[count($corpo) - 1]) === '') {
                    array_pop($corpo);
                }

                $blocos[] = [
                    'tipo' => 'codigo',
                    'linguagem' => $cerca[1] === '' ? null : mb_strtolower($cerca[1]),
                    'legenda' => self::pegarLegenda($legendaPendente),
                    'codigo' => implode("\n", $corpo),
                ];
                $temCodigo = true;

                continue;
            }

            if ($linha === '') {
                self::despejarListas($blocos, $numeradas, $marcadores);

                continue;
            }

            $ehImagem = preg_match(self::IMAGEM, $linha, $imagem) === 1;
            $ehVideo = preg_match(self::VIDEO, $linha, $video) === 1;
            $ehSecao = preg_match('/^##\s+(?!#)(.*)$/u', $linha, $secao) === 1;
            $ehSubsecao = preg_match('/^###\s+(.*)$/u', $linha, $subsecao) === 1;
            $ehNumerada = preg_match('/^\d+\.\s+(.*)$/u', $linha, $numerada) === 1;
            $ehMarcador = preg_match('/^[-*]\s+(.*)$/u', $linha, $marcador) === 1;

            if ($ehImagem) {
                self::despejarListas($blocos, $numeradas, $marcadores);
                $blocos[] = [
                    'tipo' => 'imagem',
                    'url' => $imagem[2],
                    // `alt` é string, nunca null: o atributo é obrigatório no HTML
                    // e '' tem sentido próprio ("imagem decorativa").
                    'alt' => $imagem[1],
                    'legenda' => self::pegarLegenda($legendaPendente),
                ];
            } elseif ($ehVideo) {
                self::despejarListas($blocos, $numeradas, $marcadores);
                $blocos[] = [
                    'tipo' => 'video',
                    'url' => $video[2],
                    'titulo' => $video[1],
                    'legenda' => self::pegarLegenda($legendaPendente),
                ];
            } elseif ($ehSecao) {
                self::despejarListas($blocos, $numeradas, $marcadores);
                self::despejarLegenda($blocos, $legendaPendente);
                $contaSecoes++;
                $id = self::ancora($secao[1], $contaAncoras++);
                $blocos[] = ['tipo' => 'secao', 'id' => $id, 'texto' => $secao[1], 'indice' => $contaSecoes];
                $secoes[] = ['id' => $id, 'texto' => $secao[1], 'nivel' => 2, 'indice' => $contaSecoes];
            } elseif ($ehSubsecao) {
                self::despejarListas($blocos, $numeradas, $marcadores);
                self::despejarLegenda($blocos, $legendaPendente);

                // Subtítulo aceita só ALINHAMENTO: o tamanho é o que o distingue
                // do parágrafo na hierarquia da aula, e essa escada não é do autor.
                $formato = self::lerFormato($subsecao[1], aceitaTamanho: false);
                $texto = $formato['texto'] ?? $subsecao[1];
                $id = self::ancora($texto, $contaAncoras++);

                $bloco = ['tipo' => 'subsecao', 'id' => $id, 'texto' => $texto];
                if (isset($formato['alinhamento'])) {
                    $bloco['alinhamento'] = $formato['alinhamento'];
                }

                $blocos[] = $bloco;
                $secoes[] = ['id' => $id, 'texto' => $texto, 'nivel' => 3];
            } elseif ($ehNumerada) {
                self::despejarLegenda($blocos, $legendaPendente);
                if ($marcadores !== []) {
                    self::despejarListas($blocos, $numeradas, $marcadores);
                }
                $numeradas[] = $numerada[1];
            } elseif ($ehMarcador) {
                self::despejarLegenda($blocos, $legendaPendente);
                if ($numeradas !== []) {
                    self::despejarListas($blocos, $numeradas, $marcadores);
                }
                $marcadores[] = $marcador[1];
            } else {
                self::despejarListas($blocos, $numeradas, $marcadores);
                $formato = self::lerFormato($linha, aceitaTamanho: true);

                if ($formato !== null) {
                    // Linha formatada é parágrafo por ESCOLHA do autor — nunca é
                    // lida como legenda da mídia seguinte.
                    self::despejarLegenda($blocos, $legendaPendente);
                    $bloco = ['tipo' => 'paragrafo', 'texto' => $formato['texto']];
                    foreach (['tamanho', 'alinhamento'] as $chave) {
                        if (isset($formato[$chave])) {
                            $bloco[$chave] = $formato[$chave];
                        }
                    }
                    $blocos[] = $bloco;
                } elseif (self::ehLegenda($linha)) {
                    self::despejarLegenda($blocos, $legendaPendente);
                    $legendaPendente = $linha;
                } else {
                    self::despejarLegenda($blocos, $legendaPendente);
                    $blocos[] = ['tipo' => 'paragrafo', 'texto' => $linha];
                }
            }
        }

        self::despejarListas($blocos, $numeradas, $marcadores);
        self::despejarLegenda($blocos, $legendaPendente);

        return ['blocos' => $blocos, 'secoes' => $secoes, 'temCodigo' => $temCodigo];
    }

    /**
     * Fecha a lista aberta, se houver. Chamado em toda fronteira: linha em
     * branco, troca de tipo de lista, ou qualquer bloco que não seja item.
     *
     * @param  list<array<string, mixed>>  $blocos
     * @param  list<string>  $numeradas
     * @param  list<string>  $marcadores
     */
    private static function despejarListas(array &$blocos, array &$numeradas, array &$marcadores): void
    {
        if ($numeradas !== []) {
            $blocos[] = ['tipo' => 'listaNumerada', 'itens' => $numeradas];
            $numeradas = [];
        }

        if ($marcadores !== []) {
            $blocos[] = ['tipo' => 'listaMarcadores', 'itens' => $marcadores];
            $marcadores = [];
        }
    }

    /**
     * Legenda que não encontrou mídia nenhuma vira parágrafo comum — o autor vê
     * o texto dele na tela, e não uma linha que sumiu.
     *
     * @param  list<array<string, mixed>>  $blocos
     *
     * @param-out null $legenda  sai sempre vazia: ou virou parágrafo, ou já era null.
     */
    private static function despejarLegenda(array &$blocos, ?string &$legenda): void
    {
        if ($legenda !== null) {
            $blocos[] = ['tipo' => 'paragrafo', 'texto' => $legenda];
            $legenda = null;
        }
    }

    /**
     * Consome a legenda pendente: ela passa a pertencer ao bloco que a pegou.
     *
     * @param-out null $legenda  consumir é esvaziar — a mesma legenda não pode
     *                           ser colada em dois blocos.
     */
    private static function pegarLegenda(?string &$legenda): ?string
    {
        $valor = $legenda;
        $legenda = null;

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
