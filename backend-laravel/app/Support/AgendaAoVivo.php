<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Agenda de encontros síncronos — a fonte da página `/calendario`.
 *
 * Porte de leitura do `src/utils/liveSchedule.ts`. Reúne as duas origens que o
 * sistema já tem e recorta a janela dos próximos dias:
 *
 *  - aulas ao vivo de cada curso (`LiveSession`), agendadas pelo gestor;
 *  - webinars globais (`WebinarEvent`), agendados pela coordenação.
 *
 * ## O formato da data, que é onde mora a armadilha
 *
 * `LiveSession.scheduledAt` guarda ISO **local sem fuso** (`2026-09-15T19:30`),
 * que é exatamente o que um `<input type="datetime-local">` produz. É
 * deliberado: a escola opera num único fuso, e guardar o horário literal evita
 * a aula das 19:30 aparecer como 22:30 por conversão de UTC. Por isso a leitura
 * aqui monta a data no fuso de **exibição** (`Fuso::exibicao()`), e não em UTC —
 * comparar um horário literal com um `now()` em UTC adiantaria a agenda em três
 * horas, o que no fim do dia muda o dia.
 *
 * Antes de o campo ser ISO, as sessões chegavam como "Hoje, às 19:30" ou
 * "Próxima Segunda, às 20:00" — impossível de ordenar ou filtrar. Ainda podem
 * existir registros assim, e `instanteDaAula()` devolve `null` para eles: ficam
 * de fora da agenda em vez de derrubar a página.
 *
 * ## O que NÃO foi portado, e por quê
 *
 * `toDatetimeLocalValue`, `transmissoesDoDia`, `situacaoTransmissao`,
 * `formatScheduledAt`, `dataCurta` e `horaCurta` servem ao painel do aluno e às
 * telas de gestão, que continuam em React (fases M3 restante, M4 e M5). Portar
 * agora o que ninguém chama criaria código sem consumidor — é assim que uma
 * tradução diverge do original sem ninguém notar.
 *
 * O encerramento automático das 24h já existe em PHP, em
 * `BusinessRules::liveSessionExpired()`, e não é refeito aqui.
 */
final class AgendaAoVivo
{
    /** Janela padrão da agenda, em dias. Também aparece escrito na tela. */
    public const DIAS = 30;

    /** @var list<string> */
    private const MES_ABREVIADO = [
        'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN',
        'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ',
    ];

    /** ISO local: `2026-09-15T19:30` ou `2026-09-15T19:30:00`. */
    private const ISO_LOCAL = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/';

    /** Data brasileira dos webinars: `15/09/2026`. */
    private const DATA_BR = '/^(\d{2})\/(\d{2})\/(\d{4})$/';

    /** Hora dos webinars: `19:30`. */
    private const HORA = '/^(\d{2}):(\d{2})$/';

    /**
     * Texto de um campo que vem de array solto (JSON do banco, resposta de
     * serviço). O cast direto de `mixed` esconderia um array virando "Array".
     */
    private static function texto(mixed $valor): string
    {
        if (is_string($valor)) {
            return $valor;
        }

        return is_int($valor) || is_float($valor) ? (string) $valor : '';
    }

    /**
     * Monta a data a partir das partes, recusando data inexistente.
     *
     * `CarbonImmutable::create` devolveria 03/03 para 31/02 — e a agenda
     * mostraria um encontro em dia que ninguém marcou.
     */
    private static function dataValida(int $ano, int $mes, int $dia, int $hora, int $minuto): ?CarbonImmutable
    {
        if (! checkdate($mes, $dia, $ano) || $hora > 23 || $minuto > 59) {
            return null;
        }

        return CarbonImmutable::create($ano, $mes, $dia, $hora, $minuto, 0, Fuso::exibicao()) ?: null;
    }

    /** Instante de uma aula ao vivo, ou `null` se o valor for texto livre antigo. */
    public static function instanteDaAula(?string $valor): ?CarbonImmutable
    {
        if ($valor === null || preg_match(self::ISO_LOCAL, trim($valor), $p) !== 1) {
            return null;
        }

        return self::dataValida((int) $p[1], (int) $p[2], (int) $p[3], (int) $p[4], (int) $p[5]);
    }

    /** Instante de um webinar, que guarda dia e hora em colunas separadas. */
    public static function instanteDoWebinar(?string $data, ?string $hora): ?CarbonImmutable
    {
        if ($data === null || $hora === null) {
            return null;
        }

        if (preg_match(self::DATA_BR, trim($data), $d) !== 1 || preg_match(self::HORA, trim($hora), $h) !== 1) {
            return null;
        }

        return self::dataValida((int) $d[3], (int) $d[2], (int) $d[1], (int) $h[1], (int) $h[2]);
    }

    /**
     * Encontros entre agora e `$dias` à frente, do mais próximo ao mais distante.
     *
     * `$agora` é parâmetro, e não `now()` interno, pelo mesmo motivo do
     * original: agenda que depende do relógio da máquina passa no teste hoje e
     * falha em dezembro.
     *
     * @param  list<array<string, mixed>>  $cursos  como saem de `CourseService::listCoursesFor`
     * @param  list<array<string, mixed>>  $webinars
     * @return list<array{id:string,quando:CarbonImmutable,titulo:string,contexto:string,tipo:string,duracaoMinutos:int|null,link:string|null}>
     */
    public static function montar(array $cursos, array $webinars, CarbonImmutable $agora, int $dias = self::DIAS): array
    {
        $limite = $agora->addDays($dias);
        $eventos = [];

        foreach ($cursos as $curso) {
            $sessoes = is_array($curso['liveSessions'] ?? null) ? $curso['liveSessions'] : [];

            foreach ($sessoes as $sessao) {
                if (! is_array($sessao)) {
                    continue;
                }

                $quando = self::instanteDaAula(is_string($sessao['scheduledAt'] ?? null) ? $sessao['scheduledAt'] : null);
                if ($quando === null) {
                    continue;
                }

                $duracao = $sessao['durationMinutes'] ?? null;

                $eventos[] = [
                    'id' => self::texto($sessao['id'] ?? null),
                    'quando' => $quando,
                    'titulo' => self::texto($sessao['title'] ?? null),
                    'contexto' => self::texto($curso['title'] ?? null),
                    'tipo' => 'aula',
                    'duracaoMinutos' => is_numeric($duracao) ? (int) $duracao : null,
                    /*
                     * Nunca o `meetingLink`. Ele é a chave da sala de uma turma, o
                     * catálogo anônimo já vem sem ele (ISO-01), e quem está
                     * matriculado entra pelo painel do curso. Publicar esse link
                     * numa página aberta desfaria a correção.
                     */
                    'link' => null,
                ];
            }
        }

        foreach ($webinars as $webinar) {
            $quando = self::instanteDoWebinar(
                is_string($webinar['date'] ?? null) ? $webinar['date'] : null,
                is_string($webinar['time'] ?? null) ? $webinar['time'] : null,
            );

            if ($quando === null) {
                continue;
            }

            $link = $webinar['link'] ?? null;

            $eventos[] = [
                'id' => self::texto($webinar['id'] ?? null),
                'quando' => $quando,
                'titulo' => self::texto($webinar['title'] ?? null),
                'contexto' => 'Webinar aberto',
                'tipo' => 'webinar',
                'duracaoMinutos' => null,
                'link' => is_string($link) && trim($link) !== '' ? $link : null,
            ];
        }

        $naJanela = array_values(array_filter(
            $eventos,
            static fn (array $e): bool => $e['quando'] >= $agora && $e['quando'] <= $limite,
        ));

        usort($naJanela, static fn (array $a, array $b): int => $a['quando'] <=> $b['quando']);

        return $naJanela;
    }

    /** "15" — o dia, para o quadradinho do cartão. */
    public static function dia(CarbonImmutable $quando): string
    {
        return str_pad((string) $quando->day, 2, '0', STR_PAD_LEFT);
    }

    /** "SET" — o mês abreviado. */
    public static function mes(CarbonImmutable $quando): string
    {
        return self::MES_ABREVIADO[$quando->month - 1];
    }

    /** "19:30" */
    public static function hora(CarbonImmutable $quando): string
    {
        return $quando->format('H:i');
    }

    /**
     * Distância em linguagem corrente: "hoje", "amanhã", "em 5 dias".
     *
     * A contagem é por DIA DE CALENDÁRIO, e não por 24h corridas: às 23h, um
     * encontro marcado para as 8h da manhã seguinte é "amanhã", e não "hoje" só
     * porque faltam nove horas.
     */
    public static function distanciaEmDias(CarbonImmutable $quando, CarbonImmutable $agora): string
    {
        $dias = (int) $agora->startOfDay()->diffInDays($quando->startOfDay(), false);

        if ($dias <= 0) {
            return 'hoje';
        }

        if ($dias === 1) {
            return 'amanhã';
        }

        return "em {$dias} dias";
    }
}
