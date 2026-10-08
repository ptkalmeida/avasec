<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Lê as datas que o AVASEC gravou como TEXTO e devolve data de verdade.
 *
 * Por que isto existe: a Norma C.2.4 manda usar tipo nativo de data, e sete
 * colunas deste schema (herdado do Node) guardam data em `varchar(191)`. A
 * troca do tipo não é possível sem romper contrato de API em uso, então cada
 * uma ganhou uma coluna nativa AO LADO (C.2.5, mudança aditiva) — e é esta
 * classe que traduz uma na outra, tanto no backfill quanto na gravação nova.
 *
 * ## Os formatos são cinco, não três
 *
 * O plano 11 anotava três. A varredura de 08/10/2026 nas 1.737 linhas achou
 * cinco, cada coluna com exatamente um (nenhuma linha fora de padrão):
 *
 * | Formato             | Onde                              | Linhas |
 * |---------------------|-----------------------------------|--------|
 * | `H:i:s d/m/Y`       | SecurityLog.timestamp             | 1710   |
 * | ISO 8601            | ChatMessage, DirectMessage        | 7      |
 * | `d/m/Y, H:i`        | ForumMessage.timestamp (semeado)  | 5      |
 * | `Y-m-d`             | Course.contractExpirationDate     | 7      |
 * | `d/m/Y`             | PracticalExercise, WebinarEvent   | 8      |
 *
 * `d/m/Y H:i` **sem vírgula** não aparece nos dados, mas é o que
 * `LearningService::createForumMessage` grava hoje — a vírgula veio do semeador
 * antigo. Os dois são aceitos, senão toda mensagem de fórum criada desde a
 * migração do backend ficaria sem data nativa.
 *
 * ## Fuso: naive é hora de Brasília, guardado em UTC
 *
 * `Fuso` documenta a regra da casa: **UTC é o relógio de armazenamento** e a
 * conversão acontece na borda. Os formatos sem fuso foram escritos por
 * `Fuso::agora()`, ou seja, são hora-de-parede de Brasília — interpretá-los
 * como UTC gravaria o instante errado por 3 horas. Então: naive entra como
 * fuso de exibição e sai em UTC; ISO já traz o deslocamento e só é convertido.
 *
 * Isto difere de `2026_09_03_140000_add_enviadoEm_to_QuizSubmission`, que leu
 * `d/m/Y H:i` como UTC. Lá o efeito é só na ordenação, que é monotônica e
 * portanto correta de qualquer jeito; corrigir aquela coluna mudaria um valor
 * que a API já entrega, e isso é mudança de contrato — fica sinalizada, não
 * resolvida em silêncio (AGENTS.md).
 *
 * ## Data pura não passa por fuso
 *
 * `vigenciaAte` e `prazoEm` são `DATE`. `Fuso` descreve a armadilha: meia-noite
 * UTC vira 21h do dia ANTERIOR em Brasília, e o dia do vencimento mudaria. Por
 * isso `dia()` não converte nada — devolve o dia que o texto diz.
 */
final class DataTexto
{
    /** Formatos sem fuso, com hora. Ordem importa: o mais específico primeiro. */
    private const COM_HORA = ['H:i:s d/m/Y', 'd/m/Y, H:i', 'd/m/Y H:i:s', 'd/m/Y H:i'];

    /** Formatos sem fuso e sem hora. */
    private const SO_DIA = ['d/m/Y', 'Y-m-d'];

    /**
     * Instante em UTC, pronto para gravar em coluna `DATETIME`, ou `null`.
     *
     * @param  string|null  $texto  o valor da coluna de texto
     * @param  string|null  $hora  hora numa coluna separada (`WebinarEvent.time`),
     *                             usada só quando `$texto` traz apenas o dia
     */
    public static function instante(?string $texto, ?string $hora = null): ?CarbonImmutable
    {
        $limpo = trim((string) $texto);
        if ($limpo === '') {
            return null;
        }

        foreach (self::COM_HORA as $formato) {
            $data = self::exato($formato, $limpo, Fuso::exibicao()->getName());
            if ($data !== null) {
                return $data->utc();
            }
        }

        foreach (self::SO_DIA as $formato) {
            $data = self::exato($formato, $limpo, Fuso::exibicao()->getName());
            if ($data !== null) {
                return self::comHoraSeparada($data, $hora)->utc();
            }
        }

        return self::iso($limpo);
    }

    /**
     * Dia, sem fuso e sem hora, pronto para gravar em coluna `DATE`, ou `null`.
     */
    public static function dia(?string $texto): ?CarbonImmutable
    {
        $limpo = trim((string) $texto);
        if ($limpo === '') {
            return null;
        }

        foreach (self::SO_DIA as $formato) {
            $data = self::exato($formato, $limpo, 'UTC');
            if ($data !== null) {
                return $data;
            }
        }

        // Valor com hora num campo de dia: aproveita só o dia, sem converter fuso.
        foreach (self::COM_HORA as $formato) {
            $data = self::exato($formato, $limpo, 'UTC');
            if ($data !== null) {
                return $data->startOfDay();
            }
        }

        return null;
    }

    /**
     * Interpreta num formato fixo, recusando data impossível.
     *
     * `createFromFormat` aceita '32/13/2026' e rola para o mês seguinte;
     * reformatar e comparar é o que separa data real de data inventada. Nada de
     * `strtotime`: ele lê '03/09/2026' como padrão americano e transformaria
     * 3 de setembro em 9 de março.
     */
    private static function exato(string $formato, string $valor, string $fuso): ?CarbonImmutable
    {
        try {
            // O '!' zera os campos que o formato não traz. Sem ele,
            // createFromFormat('d/m/Y H:i', ...) herda os SEGUNDOS do relógio
            // atual, e duas linhas gravadas no mesmo minuto ficariam com
            // instantes diferentes conforme a hora em que o backfill rodou.
            $data = CarbonImmutable::createFromFormat('!'.$formato, $valor, $fuso);
        } catch (Throwable) {
            return null;
        }

        if ($data === null || $data->format($formato) !== $valor) {
            return null;
        }

        return in_array($formato, self::SO_DIA, true) ? $data->startOfDay() : $data;
    }

    /** ISO 8601 (com `Z` ou deslocamento), convertido para UTC. */
    private static function iso(string $valor): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', $valor) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** Aplica `HH:MM` de uma coluna separada sobre um dia já interpretado. */
    private static function comHoraSeparada(CarbonImmutable $dia, ?string $hora): CarbonImmutable
    {
        $limpo = trim((string) $hora);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $limpo, $p) !== 1) {
            return $dia;
        }

        $h = (int) $p[1];
        $m = (int) $p[2];

        return $h <= 23 && $m <= 59 ? $dia->setTime($h, $m) : $dia;
    }
}
