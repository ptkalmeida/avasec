/**
 * Agenda de encontros síncronos — a fonte da aba Calendário.
 *
 * Reúne duas origens que o sistema já tem e formata a janela dos próximos dias:
 *  - aulas ao vivo de cada curso (`Course.liveSessions`), agendadas pelo gestor;
 *  - webinars globais (`WebinarEvent`), agendados pela coordenação.
 *
 * Sobre o formato da data: `Lesson.scheduledAt` guarda ISO local sem fuso
 * (`2026-09-15T19:30`), que é exatamente o que um `<input type="datetime-local">`
 * produz. É deliberado — a escola opera num único fuso, e guardar o horário literal
 * evita a aula das 19:30 aparecer como 22:30 por conversão de UTC. `new Date()` com
 * string sem `Z` interpreta como horário local, então a leitura casa com a escrita.
 *
 * Antes disso o campo era texto livre e as sessões chegavam como "Hoje, às 19:30" ou
 * "Próxima Segunda, às 20:00" — impossível de ordenar ou filtrar por data. Ainda
 * podem existir registros nesse formato, e `parseScheduledAt` devolve null para eles:
 * ficam fora da agenda em vez de derrubar a página.
 */

import { Course, LiveSession, WebinarEvent } from '../types';

export type AgendaKind = 'aula' | 'webinar';

export interface AgendaEvent {
  id: string;
  /** Momento do encontro, no horário local. */
  quando: Date;
  titulo: string;
  /** Curso a que pertence, ou "Webinar aberto" para os globais. */
  contexto: string;
  kind: AgendaKind;
  durationMinutes: number | null;
  /**
   * Link de acesso, quando o evento tem um que pode ser divulgado.
   *
   * Preenchido SOMENTE para webinar: webinar aberto é evento público e o link é o
   * convite dele. Aula ao vivo fica sempre null aqui de propósito — o meetingLink é
   * a chave da sala de uma turma, e o catálogo anônimo já vem sem ele (ISO-01). Quem
   * está matriculado acessa pelo painel do curso, não por uma página pública.
   */
  link: string | null;
}

const MES_ABREVIADO = [
  'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN',
  'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ',
];

/** ISO local: `2026-09-15T19:30` ou `2026-09-15T19:30:00`. */
const ISO_LOCAL = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/;

/** Data brasileira dos webinars: `15/09/2026`. */
const DATA_BR = /^(\d{2})\/(\d{2})\/(\d{4})$/;

/** Hora dos webinars: `19:30`. */
const HORA = /^(\d{2}):(\d{2})$/;

/**
 * Monta um Date a partir dos componentes, recusando data inexistente. O construtor
 * do JS "conserta" 31/02 virando 03/03 silenciosamente, o que colocaria na agenda
 * um encontro em dia que ninguém marcou.
 */
function dataValida(ano: number, mes: number, dia: number, hora: number, minuto: number): Date | null {
  const d = new Date(ano, mes - 1, dia, hora, minuto, 0, 0);
  const coerente = d.getFullYear() === ano
    && d.getMonth() === mes - 1
    && d.getDate() === dia
    && d.getHours() === hora
    && d.getMinutes() === minuto;

  return coerente ? d : null;
}

/** Data de uma aula ao vivo, ou null se o valor for texto livre do formato antigo. */
export function parseScheduledAt(value: string | null | undefined): Date | null {
  if (typeof value !== 'string') return null;

  const m = ISO_LOCAL.exec(value.trim());
  if (m === null) return null;

  return dataValida(Number(m[1]), Number(m[2]), Number(m[3]), Number(m[4]), Number(m[5]));
}

/** Data de um webinar, que guarda dia e hora em campos separados. */
export function parseWebinarDate(date: string | null | undefined, time: string | null | undefined): Date | null {
  if (typeof date !== 'string' || typeof time !== 'string') return null;

  const d = DATA_BR.exec(date.trim());
  const h = HORA.exec(time.trim());
  if (d === null || h === null) return null;

  return dataValida(Number(d[3]), Number(d[2]), Number(d[1]), Number(h[1]), Number(h[2]));
}

/** Valor que o `<input type="datetime-local">` espera, a partir de um Date. */
export function toDatetimeLocalValue(d: Date): string {
  const p = (n: number) => String(n).padStart(2, '0');

  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

/** Duas datas caem no mesmo dia do calendário local. */
export function mesmoDia(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate();
}

/**
 * Transmissões que acontecem HOJE, na ordem do horário.
 *
 * O painel do aluno listava `course.liveSessions` inteiro, sem filtro de data:
 * encontro de dias atrás continuava na tela com o botão "Entrar na Sala" ativo e
 * o texto "Você pode entrar na sala virtual e aguardar o professor". O aluno
 * entrava numa sala vazia para esperar um professor que não vinha.
 *
 * Recorte por DIA do calendário, não por "faltam menos de 24h": um encontro das
 * 19:30 de amanhã não é assunto de hoje, e um das 08:00 de hoje continua sendo
 * o encontro de hoje mesmo já tendo começado.
 *
 * `agora` é parâmetro, não `new Date()` interno — o mesmo motivo de buildAgenda:
 * filtro que depende do relógio da máquina passa hoje e falha amanhã.
 *
 * Sessão com `scheduledAt` que não é ISO local (texto livre do formato antigo,
 * "Próxima Segunda, às 20:00") fica de fora, como já acontece na agenda: sem data
 * legível não há como afirmar que é hoje, e afirmar seria o defeito de novo.
 */
export function transmissoesDoDia(
  sessions: LiveSession[] | null | undefined,
  agora: Date
): LiveSession[] {
  if (!Array.isArray(sessions)) return [];

  return sessions
    .map((s) => ({ s, quando: parseScheduledAt(s.scheduledAt) }))
    .filter((x) => x.quando !== null && mesmoDia(x.quando, agora))
    .sort((a, b) => (a.quando as Date).getTime() - (b.quando as Date).getTime())
    .map((x) => x.s);
}

/**
 * Encontros entre agora e `dias` à frente, do mais próximo ao mais distante.
 *
 * `agora` é parâmetro em vez de `new Date()` interno para o comportamento ser
 * testável — agenda que depende do relógio da máquina é agenda que passa no teste
 * hoje e falha em dezembro.
 */
export function buildAgenda(
  courses: Course[],
  webinars: WebinarEvent[],
  agora: Date,
  dias = 30
): AgendaEvent[] {
  const limite = new Date(agora.getTime() + dias * 24 * 60 * 60 * 1000);
  const eventos: AgendaEvent[] = [];

  for (const curso of courses ?? []) {
    for (const sessao of curso.liveSessions ?? []) {
      const quando = parseScheduledAt(sessao.scheduledAt);
      if (quando === null) continue;
      eventos.push({
        id: sessao.id,
        quando,
        titulo: sessao.title,
        contexto: curso.title,
        kind: 'aula',
        durationMinutes: sessao.durationMinutes ?? null,
        // Nunca o meetingLink: ver o comentário de `link` em AgendaEvent.
        link: null,
      });
    }
  }

  for (const webinar of webinars ?? []) {
    const quando = parseWebinarDate(webinar.date, webinar.time);
    if (quando === null) continue;
    eventos.push({
      id: webinar.id,
      quando,
      titulo: webinar.title,
      contexto: 'Webinar aberto',
      kind: 'webinar',
      durationMinutes: null,
      link: typeof webinar.link === 'string' && webinar.link.trim() !== '' ? webinar.link : null,
    });
  }

  return eventos
    .filter((e) => e.quando >= agora && e.quando <= limite)
    .sort((a, b) => a.quando.getTime() - b.quando.getTime());
}

/** "15" — o dia, para o quadradinho do card. */
export function formatDia(d: Date): string {
  return String(d.getDate()).padStart(2, '0');
}

/** "SET" — o mês abreviado, para o quadradinho do card. */
export function formatMes(d: Date): string {
  return MES_ABREVIADO[d.getMonth()];
}

/** "19:30" */
export function formatHora(d: Date): string {
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

/**
 * Data de uma sessão para exibir em tela: "15/09/2026 às 19:30".
 *
 * Se o valor não for uma data (registro no formato antigo, texto livre), devolve o
 * texto como está — a tela continua mostrando o que o gestor escreveu em vez de
 * "Data inválida" ou de um campo vazio.
 */
export function formatScheduledAt(value: string | null | undefined): string {
  const d = parseScheduledAt(value);
  if (d === null) return typeof value === 'string' ? value : '';

  return `${formatDia(d)}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()} às ${formatHora(d)}`;
}

/** "15/09" — para o quadradinho estreito de data no painel do aluno. */
export function dataCurta(value: string | null | undefined): string {
  const d = parseScheduledAt(value);
  if (d === null) {
    // Formato antigo ("Hoje, às 19:30"): aproveita a parte antes da vírgula.
    return typeof value === 'string' ? (value.split(',')[0] ?? '') : '';
  }

  return `${formatDia(d)}/${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/** "19:30", ou "—" quando não há hora legível. */
export function horaCurta(value: string | null | undefined): string {
  const d = parseScheduledAt(value);
  if (d === null) {
    const depoisDoAs = typeof value === 'string' ? value.split('às')[1] : undefined;

    return depoisDoAs?.trim() || '—';
  }

  return formatHora(d);
}

/**
 * Distância em linguagem corrente: "hoje", "amanhã", "em 5 dias".
 *
 * A contagem é por DIA DE CALENDÁRIO, não por 24h corridas: às 23h, um encontro
 * marcado para as 8h da manhã seguinte é "amanhã", e não "hoje" só porque faltam
 * 9 horas.
 */
export function distanciaEmDias(quando: Date, agora: Date): string {
  const meiaNoite = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  const dias = Math.round((meiaNoite(quando) - meiaNoite(agora)) / (24 * 60 * 60 * 1000));

  if (dias <= 0) return 'hoje';
  if (dias === 1) return 'amanhã';

  return `em ${dias} dias`;
}
