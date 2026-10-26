<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Edição do conteúdo de aula — a tradução PHP da metade de ESCRITA de
 * `src/utils/lessonContent.ts` (`serializeLessonBlock`, `replaceLessonBlock`,
 * `removeLessonBlock`, `insertLessonBlockAt` e `moveLessonBlock`).
 *
 * A leitura mora em `ConteudoDaAula`. Aqui o princípio é um só: **editar UM bloco
 * troca só o trecho dele no texto**, e o resto da aula continua idêntico byte a
 * byte — nunca se reserializa a aula inteira. É o que impede o editor de mexer,
 * sem querer, em conteúdo que o autor não tocou.
 *
 * Os trechos (`inicio`/`fim`) vêm de `ConteudoDaAula::analisarComTrechos` e são
 * posições em bytes. Quem chama deve ler o trecho do texto ATUAL imediatamente
 * antes de editar: o trecho de uma leitura velha aponta para o lugar errado
 * depois de qualquer outra edição.
 *
 * `EdicaoDaAulaTest` repete os casos do Vitest e, sobre o conteúdo real do
 * banco, a ida e volta (ler → serializar → ler) tem de dar o mesmo resultado.
 * Enquanto as duas implementações existirem, elas precisam concordar.
 */
final class EdicaoDaAula
{
    private const TAMANHO_PADRAO = 'normal';

    private const ALINHAMENTO_PADRAO = 'esquerda';

    /**
     * Converte um bloco de volta para o texto que o gerou.
     *
     * @param  array<string, mixed>  $bloco  bloco de `ConteudoDaAula` (os trechos são ignorados)
     */
    public static function serializar(array $bloco): string
    {
        return match ($bloco['tipo']) {
            'secao' => '## '.self::texto($bloco, 'texto'),
            'subsecao' => '### '.self::marcador($bloco['alinhamento'] ?? null, null).self::texto($bloco, 'texto'),
            'paragrafo' => self::paragrafo($bloco),
            'listaNumerada' => implode("\n", array_map(
                static fn (int $i, string $item): string => ($i + 1).'. '.$item,
                array_keys(self::itens($bloco)),
                self::itens($bloco),
            )),
            'listaMarcadores' => implode("\n", array_map(
                static fn (string $item): string => '- '.$item,
                self::itens($bloco),
            )),
            'codigo' => self::comLegenda(
                self::legenda($bloco),
                implode("\n", ['```'.(is_string($bloco['linguagem'] ?? null) ? $bloco['linguagem'] : ''), self::texto($bloco, 'codigo'), '```']),
            ),
            'imagem' => self::comLegenda(
                self::legenda($bloco),
                '!['.self::umaLinha(self::texto($bloco, 'alt')).']('.self::texto($bloco, 'url').')',
            ),
            'video' => self::comLegenda(
                self::legenda($bloco),
                '@['.self::umaLinha(self::texto($bloco, 'titulo')).']('.self::texto($bloco, 'url').')',
            ),
            default => throw new \InvalidArgumentException('Tipo de bloco desconhecido: '.(is_string($bloco['tipo']) ? $bloco['tipo'] : '?')),
        };
    }

    /** Troca o trecho pelo texto novo, preservando o resto do conteúdo. */
    public static function substituir(string $conteudo, int $inicio, int $fim, string $novo): string
    {
        return substr($conteudo, 0, $inicio).$novo.substr($conteudo, $fim);
    }

    /**
     * Remove o trecho e as linhas em branco que sobrariam coladas, para a
     * exclusão não deixar buraco de espaçamento no meio da aula.
     */
    public static function remover(string $conteudo, int $inicio, int $fim): string
    {
        $antes = (string) preg_replace('/\n{2,}\z/', "\n\n", substr($conteudo, 0, $inicio));
        $depois = (string) preg_replace('/^\n{2,}/', '', substr($conteudo, $fim));

        $junto = (string) preg_replace('/^\n+/', '', $antes.$depois);
        $junto = (string) preg_replace('/\n{3,}/', "\n\n", $junto);

        return self::cortaFim($junto);
    }

    /**
     * Insere um trecho novo na posição indicada (em bytes) — normalmente o `fim`
     * do bloco que deve ficar acima, ou 0 para o começo.
     *
     * Recebe POSIÇÃO e não um bloco de referência, de propósito: a versão em
     * TypeScript aceitou `null` como sentinela e os dois lados da fronteira o
     * liam ao contrário (o trecho escolhido no topo ia parar no rodapé). Sem
     * sentinela não há como discordarem. A posição é limitada ao tamanho do
     * conteúdo: índice inválido não estoura nem cria buraco.
     */
    public static function inserirEm(string $conteudo, int $posicao, string $texto): string
    {
        if (self::branco($conteudo)) {
            return $texto;
        }

        $limite = min(max($posicao, 0), strlen($conteudo));

        // As quebras da emenda são refeitas, não somadas: somar deixava o
        // conteúdo abrindo com linhas em branco ao inserir no começo.
        $antes = (string) preg_replace('/\n+\z/', '', substr($conteudo, 0, $limite));
        $depois = (string) preg_replace('/^\n+/', '', substr($conteudo, $limite));

        return implode("\n\n", array_filter([$antes, $texto, $depois], static fn (string $p): bool => $p !== ''));
    }

    /**
     * Troca um bloco de lugar com o vizinho de cima (`-1`) ou de baixo (`1`).
     *
     * Trabalha sobre os trechos, trocando só o texto dos dois — o resto não é
     * reescrito. A legenda anda junto porque já está dentro do trecho da figura
     * ou do código. A emenda entre os dois vira linha em branco: dois trechos
     * colados por UMA quebra ("### Título\nParágrafo") podiam, trocados de ordem,
     * se fundir num só na releitura.
     *
     * Devolve o conteúdo INALTERADO quando a troca não é possível ou mudaria o
     * texto além da ordem — ponta da lista, índice inválido, ou um parágrafo que,
     * posto logo acima de uma figura, seria lido como legenda dela ("Exemplo: …").
     * Quem chama compara com o original para saber se moveu.
     */
    public static function mover(string $conteudo, int $indice, int $direcao): string
    {
        $blocos = ConteudoDaAula::analisarComTrechos($conteudo)['blocos'];
        $alvo = $indice + $direcao;

        if (($direcao !== -1 && $direcao !== 1) || $indice < 0 || $indice >= count($blocos) || $alvo < 0 || $alvo >= count($blocos)) {
            return $conteudo;
        }

        [$primeiro, $segundo] = $direcao === -1
            ? [$blocos[$alvo], $blocos[$indice]]
            : [$blocos[$indice], $blocos[$alvo]];

        $entre = substr($conteudo, self::posicao($primeiro, 'fim'), self::posicao($segundo, 'inicio') - self::posicao($primeiro, 'fim'));
        // Entre dois trechos vizinhos só há espaço em branco; se houver outra
        // coisa, os trechos não são o que parecem e trocar estragaria o texto.
        if (! self::branco($entre)) {
            return $conteudo;
        }

        $textoPrimeiro = substr($conteudo, self::posicao($primeiro, 'inicio'), self::posicao($primeiro, 'fim') - self::posicao($primeiro, 'inicio'));
        $textoSegundo = substr($conteudo, self::posicao($segundo, 'inicio'), self::posicao($segundo, 'fim') - self::posicao($segundo, 'inicio'));

        $novo = substr($conteudo, 0, self::posicao($primeiro, 'inicio'))
            .$textoSegundo
            .(preg_match('/\n\s*\n/', $entre) === 1 ? $entre : "\n\n")
            .$textoPrimeiro
            .substr($conteudo, self::posicao($segundo, 'fim'));

        // A troca só vale se, relida, a aula tem os mesmos trechos na nova ordem.
        $antes = array_map(self::serializar(...), $blocos);
        $depois = array_map(self::serializar(...), ConteudoDaAula::analisarComTrechos($novo)['blocos']);

        $esperado = $antes;
        [$esperado[$indice], $esperado[$alvo]] = [$esperado[$alvo], $esperado[$indice]];

        return $depois === $esperado ? $novo : $conteudo;
    }

    /**
     * Marcador a gravar na frente do texto; vazio quando tudo é o padrão.
     * O alinhamento vem antes do tamanho, como no TypeScript.
     */
    private static function marcador(mixed $alinhamento, mixed $tamanho): string
    {
        $palavras = array_values(array_filter([
            is_string($alinhamento) && $alinhamento !== self::ALINHAMENTO_PADRAO ? $alinhamento : null,
            is_string($tamanho) && $tamanho !== self::TAMANHO_PADRAO ? $tamanho : null,
        ], static fn (?string $p): bool => $p !== null));

        return $palavras === [] ? '' : '{'.implode(' ', $palavras).'} ';
    }

    /**
     * Cada linha do texto vira um parágrafo na releitura, então o marcador vai em
     * todas — senão só a primeira sairia formatada.
     *
     * @param  array<string, mixed>  $bloco
     */
    private static function paragrafo(array $bloco): string
    {
        $marcador = self::marcador($bloco['alinhamento'] ?? null, $bloco['tamanho'] ?? null);

        return implode("\n", array_map(
            static fn (string $linha): string => $marcador.$linha,
            explode("\n", self::texto($bloco, 'texto')),
        ));
    }

    /**
     * Colchete e quebra de linha na descrição impediriam a releitura do que foi
     * gravado. O saneamento mora aqui, único ponto por onde todo texto passa, para
     * a idempotência de ler → serializar → ler não depender de o chamador lembrar
     * de limpar.
     */
    private static function umaLinha(string $texto): string
    {
        // Os espaços são colapsados DEPOIS da troca: "Figura [1]" viraria
        // "Figura  1 ", com o buraco de cada colchete virando um espaço solto.
        $trocado = (string) preg_replace('/[\[\]\r\n]+/u', ' ', $texto);
        $colapsado = (string) preg_replace('/\s+/u', ' ', $trocado);

        return (string) preg_replace('/^\s+|\s+$/u', '', $colapsado);
    }

    private static function comLegenda(?string $legenda, string $corpo): string
    {
        return $legenda === null ? $corpo : $legenda."\n".$corpo;
    }

    /** @param  array<string, mixed>  $bloco */
    private static function legenda(array $bloco): ?string
    {
        return is_string($bloco['legenda'] ?? null) ? $bloco['legenda'] : null;
    }

    /** @param  array<string, mixed>  $bloco */
    private static function texto(array $bloco, string $chave): string
    {
        return is_string($bloco[$chave] ?? null) ? $bloco[$chave] : '';
    }

    /**
     * @param  array<string, mixed>  $bloco
     * @return list<string>
     */
    private static function itens(array $bloco): array
    {
        $itens = $bloco['itens'] ?? [];

        return is_array($itens) ? array_values(array_map(static fn (mixed $i): string => is_string($i) ? $i : '', $itens)) : [];
    }

    /** @param  array<string, mixed>  $bloco */
    private static function posicao(array $bloco, string $chave): int
    {
        return is_int($bloco[$chave] ?? null) ? $bloco[$chave] : 0;
    }

    /** O texto é só espaço em branco? (`trim()` do JS conta os espaços unicode.) */
    private static function branco(string $texto): bool
    {
        return preg_match('/^\s*$/u', $texto) === 1;
    }

    private static function cortaFim(string $texto): string
    {
        return (string) preg_replace('/\s+\z/u', '', $texto);
    }
}
