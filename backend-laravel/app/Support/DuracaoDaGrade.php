<?php

declare(strict_types=1);

namespace App\Support;

/**
 * O tempo total de uma grade, somado a partir das aulas — porte de
 * `src/utils/courseDuration.ts`.
 *
 * `Lesson.duration` é TEXTO livre digitado por quem cadastra a aula
 * (`"15 min"`), e não um número de minutos. Somar isso exige interpretar, e
 * interpretar texto livre falha — por isso o resultado separa o que foi somado
 * do que não deu para ler, em vez de tratar aula ilegível como aula de zero
 * minuto. Uma grade com metade das durações em branco não pode anunciar um
 * total como se fosse completo.
 *
 * O card "Tempo Total Estimado" do painel antigo mostrava `~12.4 horas`
 * ESCRITO NO CÓDIGO: um curso recém-criado, sem aula, anunciava 12,4 horas.
 *
 * Os dois lados (TypeScript e PHP) existem porque o painel React ainda mostra
 * este número; quando ele sair (M7), este é o único. A equivalência foi
 * conferida rodando as duas implementações sobre as durações reais do banco e
 * sobre todos os totais de 0 a 20 horas.
 */
final class DuracaoDaGrade
{
    /**
     * Minutos de um texto de duração, ou null quando não há como saber.
     *
     * Aceita o formato do cadastro (`"15 min"`, `"90"`) e as variações de quem
     * digita à mão (`"1h"`, `"1h30"`, `"1 hora 30"`). Devolve null — e não 0 —
     * para o que não reconhece: zero somaria em silêncio, e um total errado é
     * pior que um total que se declara incompleto.
     */
    public static function minutos(?string $texto): ?int
    {
        if ($texto === null) {
            return null;
        }

        // `replace(',', '.')` do JavaScript troca só a PRIMEIRA vírgula; com
        // `str_replace` o PHP trocaria todas, e "1,5,5" seria lido como número.
        $limpo = preg_replace('/,/', '.', mb_strtolower(trim($texto)), 1) ?? '';
        if ($limpo === '') {
            return null;
        }

        // "1h30", "1 h 30", "1hora30", "2 horas"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:h|hora|horas)\s*(\d+)?\s*(?:min|minuto|minutos)?$/u', $limpo, $m) === 1) {
            $horas = (float) $m[1];
            $minutos = ($m[2] ?? '') === '' ? 0 : (int) $m[2];

            return (int) round($horas * 60 + $minutos);
        }

        // "15 min", "15min", "15 minutos", "15"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:min|minuto|minutos|m)?$/u', $limpo, $m) === 1) {
            return (int) round((float) $m[1]);
        }

        return null;
    }

    /**
     * O total para a tela. Grade vazia devolve `—`, e não `0 horas`: curso sem
     * aula não tem duração estimada, e anunciar zero como medida é o mesmo
     * vício do `~12.4 horas`.
     *
     * @param  iterable<array<string, mixed>>  $aulas
     */
    public static function texto(iterable $aulas): string
    {
        $minutos = 0;
        $semDuracao = 0;
        $contadas = 0;

        foreach ($aulas as $aula) {
            $duracao = $aula['duration'] ?? null;
            $m = self::minutos(is_string($duracao) ? $duracao : null);
            if ($m === null) {
                $semDuracao++;

                continue;
            }
            $minutos += $m;
            $contadas++;
        }

        if ($contadas === 0) {
            return '—';
        }

        // Uma decimal, e sem `~`: o total é a soma exata do que está cadastrado.
        $base = $minutos < 60
            ? $minutos.' min'
            : self::umaDecimal($minutos / 60).' h';

        if ($semDuracao === 0) {
            return $base;
        }

        return $base.' ('.$semDuracao.' '.($semDuracao === 1 ? 'aula sem duração' : 'aulas sem duração').')';
    }

    /**
     * `Number.prototype.toFixed(1)` do JavaScript, com vírgula.
     *
     * O `toFixed` arredonda o valor BINÁRIO exato e, num empate exato, escolhe
     * o maior. O PHP não faz nenhuma das duas coisas: `number_format` e `round`
     * fazem um arredondamento "corretivo" (1179 min = 19,6499… h sobe para
     * "19,7", e o JavaScript devolve "19,6"), e `sprintf('%.1f')` desempata para
     * baixo (1155 min = 19,25 h dá "19,2", e o JavaScript, "19,3").
     *
     * A conferência entre as duas implementações — de 0 a 21 h, minuto a
     * minuto — achou 83 divergências com `number_format`, 21 com `sprintf`, e
     * nenhuma com esta função, que lê as casas decimais exatas do valor e
     * desempata para cima.
     */
    private static function umaDecimal(float $horas): string
    {
        // 25 casas bastam para distinguir 19,6499… de 19,65 num double.
        [$inteira, $fracao] = explode('.', sprintf('%.25f', $horas));

        $decimos = ((int) $inteira) * 10 + (int) $fracao[0] + ((int) $fracao[1] >= 5 ? 1 : 0);

        return intdiv($decimos, 10).','.($decimos % 10);
    }
}
