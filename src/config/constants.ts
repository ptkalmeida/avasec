// Constantes de regra de negócio compartilhadas entre frontend e backend.
// FONTE ÚNICA DA VERDADE — nunca repetir estes números em outros arquivos.

/** Percentual mínimo de frequência para emissão de certificado quando o curso não define
 * um valor próprio (Course.minAttendance). */
export const DEFAULT_MIN_ATTENDANCE = 70;

/** Percentual mínimo de acertos para um quiz ser considerado aprovado (passed).
 * A nota é sempre recalculada no servidor a partir das respostas reais. */
export const QUIZ_PASS_THRESHOLD = 70;

/** Resolve o percentual mínimo efetivo de um curso.
 * Usa ?? (e não ||) de propósito: um curso configurado explicitamente com 0% é respeitado. */
export function courseMinAttendance(course: { minAttendance?: number | null } | null | undefined): number {
  return course?.minAttendance ?? DEFAULT_MIN_ATTENDANCE;
}

/** Dias após a matrícula em que o cancelamento passa a gerar restrição de rematrícula. */
export const DROPOUT_PENALTY_FREE_DAYS = 5;

/** Duração (em dias) da restrição de nova matrícula após um cancelamento tardio. */
export const DROPOUT_PENALTY_DAYS = 30;

/**
 * Horas após o horário AGENDADO em que uma transmissão ao vivo passa a valer como
 * encerrada, mesmo sem ninguém clicar em "Finalizar".
 *
 * `LiveSession.isLive` é interruptor manual, e esquecer de finalizar deixava a
 * sessão ao vivo indefinidamente — com sala aberta e ninguém dentro.
 */
export const LIVE_SESSION_AUTO_END_HOURS = 24;
