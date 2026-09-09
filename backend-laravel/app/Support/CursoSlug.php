<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Slug de curso: o nome do curso como identificador de endereço.
 *
 * Nasceu de um pedido concreto — `/aluno/curso/course-1` não diz de que curso se
 * trata, e o endereço é a única parte da tela que a pessoa copia, salva e manda
 * para outra. O custo aceito ao adotá-lo está na ADR 13: o slug passa a ser
 * **dado persistido**, com unicidade no banco e histórico dos antigos, porque a
 * alternativa (derivar do título na hora de montar o link) mata todo link salvo
 * no dia em que a coordenação corrige uma palavra do título.
 *
 * Três armadilhas dos títulos REAIS deste banco, cada uma com teste:
 *
 * 1. `Str::slug` sozinho **engole a barra**: "UX/UI Design" vira `uxui-design`,
 *    não `ux-ui-design`. Por isso os separadores viram espaço ANTES.
 * 2. Um curso chamado "Course 1" geraria o slug `course-1`, que é o **id de
 *    outro curso**. Como o roteador aceita id (links antigos) e slug, o link
 *    abriria o curso errado — silenciosamente. `ehFormaDeId()` bloqueia isso.
 * 3. Título só com pontuação ou vazio geraria slug vazio, e slug vazio some do
 *    caminho: `/aluno/curso//aula/x` não é endereço. Cai no radical `curso`.
 */
final class CursoSlug
{
    /**
     * Teto do radical. A coluna tem 191; o resto fica para o sufixo de desempate
     * (`-2`, `-13`) sem risco de o slug ser truncado pelo banco e virar outro.
     */
    private const LIMITE = 150;

    /** Radical usado quando o título não produz nada legível. */
    private const RADICAL_VAZIO = 'curso';

    /**
     * A forma de id de curso deste banco: `course-1`, `course-1787928131660`.
     * Um slug NUNCA pode ter esta forma — ver armadilha 2 no topo.
     */
    private const FORMA_DE_ID = '/^course-\d+$/';

    /** O slug tem a forma de um id de curso? */
    public static function ehFormaDeId(string $valor): bool
    {
        return preg_match(self::FORMA_DE_ID, $valor) === 1;
    }

    /**
     * Radical do slug a partir do título, sem consultar o banco.
     *
     * Não garante unicidade — isso é `unico()`, que precisa do que já existe.
     */
    public static function radical(string $titulo): string
    {
        // Separadores viram espaço para o Str::slug enxergar fronteira de palavra.
        $comEspacos = preg_replace('#[/\\:|,;+&]+#u', ' ', $titulo) ?? $titulo;

        $slug = Str::slug($comEspacos);
        if ($slug === '') {
            return self::RADICAL_VAZIO;
        }

        if (mb_strlen($slug) > self::LIMITE) {
            // Corta no limite e recua até o último hífen, para não terminar no
            // meio de uma palavra ("desenvolvimento-full-sta").
            $cortado = mb_substr($slug, 0, self::LIMITE);
            $ultimoHifen = mb_strrpos($cortado, '-');
            $slug = ($ultimoHifen !== false && $ultimoHifen > 0)
                ? mb_substr($cortado, 0, $ultimoHifen)
                : $cortado;
        }

        // Forma de id só aqui, DEPOIS do corte: cortar depois poderia recriá-la.
        return self::ehFormaDeId($slug) ? self::RADICAL_VAZIO.'-'.$slug : $slug;
    }

    /**
     * Slug livre a partir do título, dado o conjunto do que já está tomado.
     *
     * `$tomados` tem de incluir os slugs ATUAIS e os APOSENTADOS: um slug
     * aposentado ainda resolve para o curso antigo, então reaproveitá-lo faria o
     * link salvo de um curso abrir outro. É o mesmo cuidado do `withTrashed()`
     * em upsert (ADR 12): o que não aparece na consulta continua existindo.
     *
     * @param  iterable<string>  $tomados
     */
    public static function unico(string $titulo, iterable $tomados): string
    {
        $radical = self::radical($titulo);

        $ocupados = [];
        foreach ($tomados as $t) {
            $ocupados[$t] = true;
        }

        if (! isset($ocupados[$radical])) {
            return $radical;
        }

        // Sufixo numérico, e não hash: `curso-de-teste-2` continua legível para
        // quem lê o endereço, que é a razão de existir deste arquivo.
        for ($n = 2; $n < 10000; $n++) {
            $tentativa = $radical.'-'.$n;
            if (! isset($ocupados[$tentativa])) {
                return $tentativa;
            }
        }

        // Inalcançável na prática; melhor um slug feio que uma colisão calada.
        return $radical.'-'.bin2hex(random_bytes(4));
    }
}
