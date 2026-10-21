<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * As operações do editor de material didático, sobre o TEXTO da aula.
 *
 * É a tradução do que `LessonContentEditor.tsx` e `LessonMediaForm.tsx` faziam
 * no navegador: mover, remover, inserir e atualizar UM bloco. Cada operação
 * recebe o texto atual e devolve o texto novo — nada aqui toca em banco, e quem
 * grava (com a trava de versão) é `CourseService::editarConteudoDaAula`.
 *
 * Quem escreve o texto continua sendo `EdicaoDaAula`: editar um bloco troca só o
 * trecho dele, e o resto da aula fica idêntico byte a byte.
 *
 * O bloco é endereçado pelo ÍNDICE na leitura do texto atual. O índice só vale
 * para o texto que a pessoa viu; por isso a trava de versão existe, e por isso
 * estas funções releem o texto que recebem em vez de confiar em posições vindas
 * de fora.
 *
 * Erro de quem escreve é `InvalidArgumentException` com a mensagem em português,
 * para a tela mostrar como está.
 */
final class MaterialDaAula
{
    /** Tipos que o "Adicionar" oferece. Código não entra: ver `TEXTO_INICIAL`. */
    public const TIPOS_NOVOS = ['secao', 'subsecao', 'paragrafo', 'listaNumerada', 'listaMarcadores', 'imagem', 'video'];

    /**
     * O texto inicial de cada tipo que nasce pronto. "Código" NÃO é oferecido de
     * propósito: quem edita o texto das aulas é gestor de conteúdo, não
     * programador, e um bloco que pede linguagem e indentação convida ao erro. Os
     * blocos de código que JÁ existem continuam editáveis — tirar a edição
     * tornaria esse conteúdo impossível de corrigir.
     */
    public const TEXTO_INICIAL = [
        'secao' => '## Título da seção',
        'subsecao' => '### Subtítulo',
        'paragrafo' => 'Escreva o parágrafo aqui.',
        'listaNumerada' => "1. Primeiro item\n2. Segundo item",
        'listaMarcadores' => "- Primeiro item\n- Segundo item",
    ];

    /** Tipos de texto entre os quais um trecho pode ser convertido. */
    public const CONVERSIVEIS = ['secao', 'subsecao', 'paragrafo', 'listaNumerada', 'listaMarcadores'];

    /** Linguagens que o bloco de código realmente realça. */
    public const LINGUAGENS = ['java', 'javascript', 'typescript', 'php', 'python', 'sql', 'json', 'css', 'html', 'bash'];

    /** Imagem e vídeo só cabem na linha se o endereço não tiver espaço nem parêntese. */
    private const ENDERECO_DE_MIDIA = '/^[^()\s]+$/u';

    /**
     * O bloco de índice `$indice`, com o trecho.
     *
     * @return array<string, mixed>
     */
    public static function bloco(string $conteudo, int $indice): array
    {
        $blocos = ConteudoDaAula::analisarComTrechos($conteudo)['blocos'];

        if ($indice < 0 || $indice >= count($blocos)) {
            throw new InvalidArgumentException('Esse trecho não existe mais. Recarregue a página para ver o texto atual.');
        }

        return $blocos[$indice];
    }

    /**
     * Sobe ou desce um trecho. Nas pontas não faz nada; e se a troca mudaria o
     * texto além da ordem (um parágrafo que, acima de uma figura, viraria legenda
     * dela), recusa em vez de mexer.
     */
    public static function mover(string $conteudo, int $indice, string $direcao): string
    {
        $passo = $direcao === 'up' ? -1 : 1;
        $total = count(ConteudoDaAula::analisarComTrechos($conteudo)['blocos']);
        $alvo = $indice + $passo;

        if ($indice < 0 || $indice >= $total) {
            throw new InvalidArgumentException('Esse trecho não existe mais. Recarregue a página para ver o texto atual.');
        }
        if ($alvo < 0 || $alvo >= $total) {
            return $conteudo;
        }

        $novo = EdicaoDaAula::mover($conteudo, $indice, $passo);
        if ($novo === $conteudo) {
            throw new InvalidArgumentException('Este trecho não pode ir para lá sem mudar o texto (por exemplo, um parágrafo que viraria legenda da figura). Mova o outro trecho, ou edite este.');
        }

        return $novo;
    }

    /** Remove o trecho, e a legenda que anda junto com ele. */
    public static function remover(string $conteudo, int $indice): string
    {
        $bloco = self::bloco($conteudo, $indice);

        return EdicaoDaAula::remover($conteudo, self::posicao($bloco, 'inicio'), self::posicao($bloco, 'fim'));
    }

    /**
     * Insere um trecho já escrito depois do bloco `$apos`; `-1` insere no começo.
     *
     * Índice fora da faixa é engano de quem chama, e não pedido do autor: melhor
     * recusar do que despejar o trecho no fim da aula sem avisar (o erro que a
     * versão em TypeScript teve com o `null` como sentinela).
     */
    public static function inserir(string $conteudo, int $apos, string $texto): string
    {
        if ($apos < -1) {
            throw new InvalidArgumentException('Posição inválida para o novo trecho.');
        }

        $posicao = 0;
        if ($apos >= 0) {
            $posicao = self::posicao(self::bloco($conteudo, $apos), 'fim');
        }

        return EdicaoDaAula::inserirEm($conteudo, $posicao, $texto);
    }

    /** Insere o trecho-modelo de um tipo de texto. */
    public static function inserirModelo(string $conteudo, int $apos, string $tipo): string
    {
        if (! isset(self::TEXTO_INICIAL[$tipo])) {
            throw new InvalidArgumentException('Escolha o tipo de trecho a adicionar.');
        }

        return self::inserir($conteudo, $apos, self::TEXTO_INICIAL[$tipo]);
    }

    /**
     * Atualiza um trecho com o que veio do formulário.
     *
     * `$campos` é a forma plana do formulário: `tipo`, `texto`, `tamanho`,
     * `alinhamento` (texto); `linguagem`, `legenda`, `codigo` (código); `url`,
     * `descricao`, `legenda` (mídia).
     *
     * @param  array<string, string>  $campos
     */
    public static function atualizar(string $conteudo, int $indice, array $campos): string
    {
        $bloco = self::bloco($conteudo, $indice);
        $tipoAtual = is_string($bloco['tipo'] ?? null) ? $bloco['tipo'] : '';

        $novo = match ($tipoAtual) {
            'codigo' => self::blocoDeCodigo($bloco, $campos),
            'imagem', 'video' => self::blocoDeMidia($tipoAtual, $campos),
            default => self::blocoDeTexto($campos),
        };

        return EdicaoDaAula::substituir(
            $conteudo,
            self::posicao($bloco, 'inicio'),
            self::posicao($bloco, 'fim'),
            EdicaoDaAula::serializar($novo),
        );
    }

    /**
     * O trecho de uma mídia nova, pronto para `inserir`.
     *
     * @param  array<string, string>  $campos  `url`, `descricao`, `legenda`
     */
    public static function midiaNova(string $tipo, array $campos): string
    {
        if ($tipo !== 'imagem' && $tipo !== 'video') {
            throw new InvalidArgumentException('Escolha o tipo de trecho a adicionar.');
        }

        return EdicaoDaAula::serializar(self::blocoDeMidia($tipo, $campos));
    }

    /**
     * Texto → bloco, no tipo escolhido (a conversão entre tipos).
     *
     * Título é uma linha por natureza, então linhas soltas viram uma só; parágrafo
     * preserva as quebras (cada linha vira um parágrafo); lista usa cada linha como
     * item. A formatação vai só para quem a aceita: subtítulo leva o alinhamento,
     * parágrafo leva os dois — converter para seção ou lista a descarta, porque
     * ali ela não existe.
     *
     * @param  array<string, string>  $campos
     * @return array<string, mixed>
     */
    private static function blocoDeTexto(array $campos): array
    {
        $tipo = $campos['tipo'] ?? '';
        if (! in_array($tipo, self::CONVERSIVEIS, true)) {
            throw new InvalidArgumentException('Escolha o tipo do trecho.');
        }

        $partes = array_values(array_filter(
            array_map(static fn (string $l): string => trim($l), preg_split('/\r\n|\r|\n/', $campos['texto'] ?? '') ?: []),
            static fn (string $l): bool => $l !== '',
        ));
        // Trecho vazio não vira bloco: gravaria "## " e sujaria o conteúdo.
        if ($partes === []) {
            throw new InvalidArgumentException('Escreva algo antes de aplicar.');
        }

        $tamanho = $campos['tamanho'] ?? 'normal';
        $alinhamento = $campos['alinhamento'] ?? 'esquerda';
        if (! in_array($tamanho, ConteudoDaAula::TAMANHOS, true) || ! in_array($alinhamento, ConteudoDaAula::ALINHAMENTOS, true)) {
            throw new InvalidArgumentException('Tamanho ou alinhamento inválido.');
        }

        return match ($tipo) {
            'secao' => ['tipo' => 'secao', 'texto' => implode(' ', $partes)],
            'subsecao' => ['tipo' => 'subsecao', 'texto' => implode(' ', $partes), 'alinhamento' => $alinhamento],
            'paragrafo' => ['tipo' => 'paragrafo', 'texto' => implode("\n", $partes), 'tamanho' => $tamanho, 'alinhamento' => $alinhamento],
            'listaNumerada' => ['tipo' => 'listaNumerada', 'itens' => $partes],
            default => ['tipo' => 'listaMarcadores', 'itens' => $partes],
        };
    }

    /**
     * @param  array<string, mixed>  $atual
     * @param  array<string, string>  $campos
     * @return array<string, mixed>
     */
    private static function blocoDeCodigo(array $atual, array $campos): array
    {
        $linguagem = mb_strtolower(trim($campos['linguagem'] ?? ''));
        // A linguagem que o bloco já tem continua válida mesmo fora da lista: tirá-la
        // ao salvar mudaria um conteúdo que ninguém pediu para mudar.
        $linguagemAtual = is_string($atual['linguagem'] ?? null) ? $atual['linguagem'] : null;
        if ($linguagem === '') {
            $linguagem = $linguagemAtual ?? '';
        } elseif (! in_array($linguagem, self::LINGUAGENS, true) && $linguagem !== $linguagemAtual) {
            throw new InvalidArgumentException('Escolha uma das linguagens da lista.');
        }

        // O código vem como foi digitado: a indentação é dele. Só a quebra de linha
        // do Windows é normalizada, e uma cerca solta dentro dele é recusada — fecharia o
        // bloco no meio e despejaria o resto como texto da aula.
        $codigo = str_replace("\r\n", "\n", $campos['codigo'] ?? '');
        foreach (explode("\n", $codigo) as $linha) {
            if (trim($linha) === '```') {
                throw new InvalidArgumentException('O código não pode ter uma linha só com três crases: ela fecharia o bloco.');
            }
        }

        return [
            'tipo' => 'codigo',
            'linguagem' => $linguagem === '' ? null : $linguagem,
            'legenda' => self::legenda($campos['legenda'] ?? ''),
            'codigo' => $codigo,
        ];
    }

    /**
     * @param  array<string, string>  $campos
     * @return array<string, mixed>
     */
    private static function blocoDeMidia(string $tipo, array $campos): array
    {
        $url = trim($campos['url'] ?? '');
        $descricao = trim((string) preg_replace('/\s+/u', ' ', $campos['descricao'] ?? ''));

        if ($url === '') {
            throw new InvalidArgumentException($tipo === 'imagem' ? 'Escolha a imagem.' : 'Informe o link do vídeo.');
        }
        if (preg_match(self::ENDERECO_DE_MIDIA, $url) !== 1) {
            throw new InvalidArgumentException('O endereço não pode ter espaço nem parênteses.');
        }
        if ($descricao === '') {
            throw new InvalidArgumentException($tipo === 'imagem' ? 'Descreva a imagem: é o que o leitor de tela lê.' : 'Dê um título ao vídeo.');
        }

        if ($tipo === 'video') {
            if (VideoSource::tryParse($url) === null) {
                throw new InvalidArgumentException('Não reconhecemos esse link. Copie o endereço direto do vídeo no YouTube — ele começa com https://www.youtube.com ou https://youtu.be.');
            }

            return ['tipo' => 'video', 'url' => $url, 'titulo' => $descricao, 'legenda' => self::legenda($campos['legenda'] ?? '')];
        }

        if (! SafeUrl::isValid($url)) {
            throw new InvalidArgumentException('O endereço da imagem não é válido.');
        }

        return ['tipo' => 'imagem', 'url' => $url, 'alt' => $descricao, 'legenda' => self::legenda($campos['legenda'] ?? '')];
    }

    /**
     * A legenda, ou null. Uma legenda fora do padrão (começar por Figura, Imagem,
     * Gráfico, Vídeo, Tabela, Quadro, Código ou Exemplo, com número) viraria um
     * parágrafo solto na releitura: o editor recusa e explica.
     */
    private static function legenda(string $texto): ?string
    {
        $limpa = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if ($limpa === '') {
            return null;
        }
        if (! ConteudoDaAula::ehLegenda($limpa)) {
            throw new InvalidArgumentException('Comece a legenda por Figura, Imagem, Gráfico, Vídeo, Tabela ou Quadro, seguido do número — senão ela vira um parágrafo solto em vez de legenda.');
        }

        return $limpa;
    }

    /** @param  array<string, mixed>  $bloco */
    private static function posicao(array $bloco, string $chave): int
    {
        return is_int($bloco[$chave] ?? null) ? $bloco[$chave] : 0;
    }
}
