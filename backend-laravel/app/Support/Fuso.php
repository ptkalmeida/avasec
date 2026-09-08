<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Fuso de EXIBIÇÃO. Guardar em UTC, apresentar no fuso de quem lê.
 *
 * O sintoma: `config('app.timezone')` é `UTC` e a escola opera em BRT, então
 * `now()->format('d/m/Y H:i')` produzia 3 horas adiante em toda tela — uma
 * entrega feita às 17:08 aparecia como 20:08, e um certificado emitido às 22h
 * saía datado do dia seguinte.
 *
 * A correção NÃO foi trocar `app.timezone` para `America/Sao_Paulo`, e a razão
 * importa mais que a escolha:
 *
 * 1. Todo `DATETIME`/`TIMESTAMP` já gravado foi escrito em hora-de-parede UTC.
 *    Trocar o fuso da aplicação não move um byte no banco — passa a INTERPRETAR
 *    os mesmos bytes como BRT, e todo o histórico de notas, entregas e
 *    inativações passaria a ser lido 3 horas depois do que aconteceu. Erro
 *    silencioso, retroativo e em registro acadêmico.
 * 2. O MySQL deste ambiente também roda em UTC (`NOW()` concorda com o
 *    `now()` do Laravel). Mover só a aplicação faria o relógio do banco e o do
 *    app discordarem entre si — dois relógios num sistema só.
 *
 * Então UTC continua sendo o relógio de armazenamento, único e sem ambiguidade,
 * e a conversão acontece na borda, onde o valor vira texto para uma pessoa.
 *
 * ## A armadilha: isto serve para INSTANTE, não para data pura
 *
 * `Certificate.issueDate` é coluna `DATE`. Uma data pura não tem instante: o
 * Carbon a materializa à meia-noite, e converter meia-noite UTC para BRT devolve
 * 21h do DIA ANTERIOR — formatar em `d/m/Y` depois disso troca o dia do
 * certificado. Por isso os dois pontos que formatam `issueDate` NÃO passam por
 * aqui, e há teste prendendo esse comportamento.
 */
final class Fuso
{
    /** Fuso em que datas e horas são mostradas a pessoas. */
    public static function exibicao(): DateTimeZone
    {
        $nome = config('app.display_timezone');

        return new DateTimeZone(is_string($nome) && $nome !== '' ? $nome : 'UTC');
    }

    /** Agora, já no fuso de exibição. Use no lugar de `now()` ao formatar. */
    public static function agora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::exibicao());
    }

    /**
     * Converte um instante gravado (UTC) para o fuso de exibição.
     *
     * Só para coluna com hora (`DATETIME`/`TIMESTAMP`). Nunca para `DATE` — ver
     * a armadilha na descrição da classe.
     */
    public static function de(DateTimeInterface $instante): CarbonImmutable
    {
        return CarbonImmutable::instance($instante)->setTimezone(self::exibicao());
    }

    /**
     * Igual a `de()`, mas aceita e propaga `null`.
     *
     * Existe separada em vez de `de()` virar nullable: coluna que pode estar
     * vazia (`inativadoEm`) e instante que acabou de ser criado são casos
     * diferentes, e um retorno `?CarbonImmutable` no segundo caso obrigaria a
     * silenciar o tipo em quem chama — que é onde nasce o `?->` que engole erro.
     */
    public static function local(?DateTimeInterface $instante): ?CarbonImmutable
    {
        return $instante === null ? null : self::de($instante);
    }
}
