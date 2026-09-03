import { describe, it, expect } from 'vitest';
import {
  parseScheduledAt,
  parseWebinarDate,
  toDatetimeLocalValue,
  buildAgenda,
  formatDia,
  formatMes,
  formatHora,
  distanciaEmDias,
  mesmoDia,
  transmissoesDoDia,
} from '../../src/utils/liveSchedule';
import { Course, LiveSession, WebinarEvent } from '../../src/types';

/** 10/09/2026 às 12:00, horário local — o "agora" de todos os testes. */
const AGORA = new Date(2026, 8, 10, 12, 0);

const curso = (id: string, title: string, sessoes: Array<[string, string, string]>): Course => ({
  id,
  title,
  description: 'x',
  category: 'y',
  thumbnail: 't',
  instructorName: 'Prof',
  lessons: [],
  liveSessions: sessoes.map(([sid, stitle, scheduledAt]) => ({
    id: sid,
    courseId: id,
    title: stitle,
    scheduledAt,
    durationMinutes: 60,
    meetingLink: '',
    isLive: false,
  })),
} as unknown as Course);

const webinar = (id: string, title: string, date: string, time: string): WebinarEvent =>
  ({ id, title, date, time, description: 'd', link: '#', image: 'i' } as unknown as WebinarEvent);

describe('parseScheduledAt', () => {
  it('lê ISO local com e sem segundos', () => {
    expect(parseScheduledAt('2026-09-15T19:30')).toEqual(new Date(2026, 8, 15, 19, 30));
    expect(parseScheduledAt('2026-09-15T19:30:00')).toEqual(new Date(2026, 8, 15, 19, 30));
  });

  it('trata a data como horário LOCAL, não UTC', () => {
    // Guardar literal é decisão: a aula das 19:30 não pode virar 22:30 na tela.
    const d = parseScheduledAt('2026-09-15T19:30');
    expect(d?.getHours()).toBe(19);
    expect(d?.getMinutes()).toBe(30);
  });

  it('devolve null para o texto livre do formato antigo, sem quebrar', () => {
    // Estes valores existiam de verdade no banco antes do campo virar data.
    expect(parseScheduledAt('Hoje, às 19:30')).toBeNull();
    expect(parseScheduledAt('Amanhã, às 18:00')).toBeNull();
    expect(parseScheduledAt('Próxima Segunda, às 20:00')).toBeNull();
  });

  it('recusa data inexistente em vez de deslizar para o mês seguinte', () => {
    // new Date(2026, 1, 31) viraria 03/03 em silêncio.
    expect(parseScheduledAt('2026-02-31T10:00')).toBeNull();
    expect(parseScheduledAt('2026-13-01T10:00')).toBeNull();
    expect(parseScheduledAt('2026-09-15T25:00')).toBeNull();
  });

  it('recusa vazio, nulo e indefinido', () => {
    expect(parseScheduledAt('')).toBeNull();
    expect(parseScheduledAt(null)).toBeNull();
    expect(parseScheduledAt(undefined)).toBeNull();
  });
});

describe('parseWebinarDate', () => {
  it('lê data brasileira com hora separada', () => {
    expect(parseWebinarDate('15/09/2026', '19:00')).toEqual(new Date(2026, 8, 15, 19, 0));
  });

  it('recusa formato inválido ou data inexistente', () => {
    expect(parseWebinarDate('2026-09-15', '19:00')).toBeNull();
    expect(parseWebinarDate('31/02/2026', '10:00')).toBeNull();
    expect(parseWebinarDate('15/09/2026', '19h')).toBeNull();
  });
});

describe('toDatetimeLocalValue', () => {
  it('produz o valor que o input datetime-local espera, com zero à esquerda', () => {
    expect(toDatetimeLocalValue(new Date(2026, 0, 5, 9, 7))).toBe('2026-01-05T09:07');
  });

  it('faz ida e volta com parseScheduledAt', () => {
    const original = '2026-09-15T19:30';
    expect(toDatetimeLocalValue(parseScheduledAt(original)!)).toBe(original);
  });
});

describe('buildAgenda', () => {
  const cursos = [
    curso('c1', 'Design de Interfaces', [
      ['s1', 'Mentoria de Wireframes', '2026-09-15T19:30'],
      ['s2', 'Feedback de Portfólio', '2026-09-22T18:00'],
      ['s3', 'Encontro que já passou', '2026-09-01T19:00'],
      ['s4', 'Encontro além dos 30 dias', '2026-11-20T19:00'],
      ['s5', 'Sessão do formato antigo', 'Próxima Segunda, às 20:00'],
    ]),
    curso('c2', 'Economia Criativa', [
      ['s6', 'Abertura da turma', '2026-09-12T20:00'],
    ]),
  ];
  const webinars = [
    webinar('w1', 'IA no Design', '18/09/2026', '19:00'),
    webinar('w2', 'Webinar antigo', '01/06/2026', '19:00'),
  ];

  it('devolve só a janela de 30 dias, do mais próximo ao mais distante', () => {
    const agenda = buildAgenda(cursos, webinars, AGORA);

    expect(agenda.map((e) => e.titulo)).toEqual([
      'Abertura da turma',        // 12/09
      'Mentoria de Wireframes',  // 15/09
      'IA no Design',            // 18/09 (webinar)
      'Feedback de Portfólio',   // 22/09
    ]);
  });

  it('descarta o que já passou, o que está além da janela e o formato antigo', () => {
    const titulos = buildAgenda(cursos, webinars, AGORA).map((e) => e.titulo);

    expect(titulos).not.toContain('Encontro que já passou');
    expect(titulos).not.toContain('Encontro além dos 30 dias');
    expect(titulos).not.toContain('Sessão do formato antigo');
    expect(titulos).not.toContain('Webinar antigo');
  });

  it('identifica a origem de cada encontro', () => {
    const agenda = buildAgenda(cursos, webinars, AGORA);
    const aula = agenda.find((e) => e.titulo === 'Mentoria de Wireframes')!;
    const web = agenda.find((e) => e.titulo === 'IA no Design')!;

    expect(aula.kind).toBe('aula');
    expect(aula.contexto).toBe('Design de Interfaces');
    expect(aula.durationMinutes).toBe(60);

    expect(web.kind).toBe('webinar');
    expect(web.contexto).toBe('Webinar aberto');
  });

  it('respeita uma janela diferente quando pedida', () => {
    expect(buildAgenda(cursos, webinars, AGORA, 5).map((e) => e.titulo)).toEqual(['Abertura da turma']);
  });

  it('lida com listas vazias e com curso sem sessão', () => {
    expect(buildAgenda([], [], AGORA)).toEqual([]);
    expect(buildAgenda([curso('c9', 'Vazio', [])], [], AGORA)).toEqual([]);
  });
});

describe('formatadores', () => {
  const d = new Date(2026, 8, 5, 9, 7);

  it('formata dia, mês e hora para o card', () => {
    expect(formatDia(d)).toBe('05');
    expect(formatMes(d)).toBe('SET');
    expect(formatHora(d)).toBe('09:07');
  });
});

describe('distanciaEmDias', () => {
  it('conta por dia de calendário, não por 24 horas corridas', () => {
    // Às 23h, um encontro às 8h do dia seguinte é "amanhã" — faltam 9 horas, mas
    // é outro dia, e é assim que quem lê a agenda pensa.
    const noite = new Date(2026, 8, 10, 23, 0);
    expect(distanciaEmDias(new Date(2026, 8, 11, 8, 0), noite)).toBe('amanhã');
  });

  it('diz hoje, amanhã e em N dias', () => {
    expect(distanciaEmDias(new Date(2026, 8, 10, 19, 0), AGORA)).toBe('hoje');
    expect(distanciaEmDias(new Date(2026, 8, 11, 19, 0), AGORA)).toBe('amanhã');
    expect(distanciaEmDias(new Date(2026, 8, 15, 19, 0), AGORA)).toBe('em 5 dias');
  });

  it('atravessa a virada de mês sem se perder', () => {
    expect(distanciaEmDias(new Date(2026, 9, 1, 10, 0), new Date(2026, 8, 30, 10, 0))).toBe('amanhã');
  });
});

describe('mesmoDia', () => {
  it('compara dia de calendário, ignorando a hora', () => {
    expect(mesmoDia(new Date(2026, 8, 10, 0, 1), new Date(2026, 8, 10, 23, 59))).toBe(true);
    expect(mesmoDia(new Date(2026, 8, 10, 23, 59), new Date(2026, 8, 11, 0, 1))).toBe(false);
  });

  it('mesmo dia e mês de anos diferentes não é o mesmo dia', () => {
    expect(mesmoDia(new Date(2026, 8, 10, 12, 0), new Date(2025, 8, 10, 12, 0))).toBe(false);
  });
});

describe('transmissoesDoDia', () => {
  const sessao = (id: string, scheduledAt: string, over: Partial<LiveSession> = {}): LiveSession => ({
    id,
    courseId: 'course-1',
    title: `Encontro ${id}`,
    scheduledAt,
    durationMinutes: 60,
    meetingLink: 'https://meet.example/x',
    isLive: false,
    ...over,
  });

  // 10/09/2026, 14:00 — a referência de "hoje" para este bloco.
  const HOJE = new Date(2026, 8, 10, 14, 0);

  it('deixa de fora o encontro de dias atrás', () => {
    // O defeito relatado: encontros de 01/09 e 02/09 continuavam na tela em 03/09
    // com o botão "Entrar na Sala" ativo, convidando a esperar numa sala vazia.
    const passadas = [
      sessao('s1', '2026-09-01T19:30'),
      sessao('s2', '2026-09-02T18:00'),
    ];
    expect(transmissoesDoDia(passadas, new Date(2026, 8, 3, 10, 0))).toEqual([]);
  });

  it('mostra a de hoje mesmo já tendo começado', () => {
    // Encontro das 08:00 visto às 14:00 ainda é o encontro de hoje.
    const r = transmissoesDoDia([sessao('manha', '2026-09-10T08:00')], HOJE);
    expect(r.map((s) => s.id)).toEqual(['manha']);
  });

  it('não antecipa a de amanhã', () => {
    expect(transmissoesDoDia([sessao('amanha', '2026-09-11T19:30')], HOJE)).toEqual([]);
  });

  it('a de amanhã aparece só quando amanhã chega', () => {
    const s = [sessao('amanha', '2026-09-11T19:30')];
    // Faltando 30 minutos, ainda é outro dia: não aparece.
    expect(transmissoesDoDia(s, new Date(2026, 8, 10, 23, 59))).toEqual([]);
    // Um minuto depois, virou hoje.
    expect(transmissoesDoDia(s, new Date(2026, 8, 11, 0, 1)).map((x) => x.id)).toEqual(['amanha']);
  });

  it('ordena as de hoje pelo horário', () => {
    const r = transmissoesDoDia([
      sessao('noite', '2026-09-10T20:00'),
      sessao('manha', '2026-09-10T08:00'),
      sessao('tarde', '2026-09-10T15:30'),
    ], HOJE);
    expect(r.map((s) => s.id)).toEqual(['manha', 'tarde', 'noite']);
  });

  it('separa as de hoje das de outros dias na mesma lista', () => {
    const r = transmissoesDoDia([
      sessao('ontem', '2026-09-09T19:30'),
      sessao('hoje', '2026-09-10T19:30'),
      sessao('amanha', '2026-09-11T19:30'),
    ], HOJE);
    expect(r.map((s) => s.id)).toEqual(['hoje']);
  });

  it('descarta data em formato antigo de texto livre', () => {
    // Sem data legível não há como afirmar que é hoje — e afirmar era o defeito.
    const r = transmissoesDoDia([
      sessao('legado', 'Próxima Segunda, às 20:00'),
      sessao('hoje', '2026-09-10T19:30'),
    ], HOJE);
    expect(r.map((s) => s.id)).toEqual(['hoje']);
  });

  it('descarta data inexistente em vez de deslizar para o mês seguinte', () => {
    expect(transmissoesDoDia([sessao('ruim', '2026-02-31T19:30')], HOJE)).toEqual([]);
  });

  it('lista ausente ou vazia devolve vazio, não quebra', () => {
    expect(transmissoesDoDia(undefined, HOJE)).toEqual([]);
    expect(transmissoesDoDia(null, HOJE)).toEqual([]);
    expect(transmissoesDoDia([], HOJE)).toEqual([]);
  });
});
