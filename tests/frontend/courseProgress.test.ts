import { describe, it, expect } from 'vitest';
import {
  registroDoAluno,
  aulasConcluidas,
  presencasAoVivo,
  progressoPercent,
  frequenciaPercent,
  cursoConcluido,
  mediaProgresso,
} from '../../src/utils/courseProgress';
import { Course, StudentProgress } from '../../src/types';

const curso = (id: string, aulas: string[], aoVivo: string[] = []): Course => ({
  id,
  title: `Curso ${id}`,
  description: 'd',
  category: 'Design',
  instructorId: 'prof-1',
  instructorName: 'Prof. Ana',
  lessons: aulas.map((lid, i) => ({ id: lid, title: lid, order: i + 1 })),
  liveSessions: aoVivo.map((sid) => ({ id: sid })),
} as unknown as Course);

const registro = (
  courseId: string,
  concluidas: string[],
  presencas: string[] = [],
  userId = 'aluno-1'
): StudentProgress => ({
  userId,
  studentName: 'João Silva',
  courseId,
  completedLessons: concluidas,
  attendedLiveSessions: presencas,
});

describe('aulas concluídas', () => {
  it('conta só as aulas que ainda existem no curso', () => {
    // O defeito real: o curso tinha 1 aula e o registro guardava 2 ids de aulas
    // já apagadas — 2/1 = 200%.
    const c = curso('c1', ['a1']);
    const r = registro('c1', ['a1', 'apagada-1', 'apagada-2']);

    expect(aulasConcluidas(c, r)).toBe(1);
    expect(progressoPercent(c, r)).toBe(100);
  });

  it('sem registro é zero, não indefinido', () => {
    expect(aulasConcluidas(curso('c1', ['a1']), undefined)).toBe(0);
    expect(progressoPercent(curso('c1', ['a1']), undefined)).toBe(0);
  });

  it('curso sem aulas não divide por zero', () => {
    expect(progressoPercent(curso('c1', []), registro('c1', ['fantasma']))).toBe(0);
  });

  it('progresso nunca passa de 100', () => {
    const c = curso('c1', ['a1', 'a2']);
    const r = registro('c1', ['a1', 'a2', 'a1', 'orfa']);
    expect(progressoPercent(c, r)).toBeLessThanOrEqual(100);
  });
});

describe('presença ao vivo', () => {
  it('ignora sessão que saiu da agenda do curso', () => {
    const c = curso('c1', ['a1'], ['s1']);
    const r = registro('c1', ['a1'], ['s1', 's-removida']);
    expect(presencasAoVivo(c, r)).toBe(1);
  });

  it('curso sem sessões ao vivo não quebra', () => {
    const c = curso('c1', ['a1']);
    expect(presencasAoVivo(c, registro('c1', ['a1'], ['s1']))).toBe(0);
  });
});

describe('frequência', () => {
  it('soma aulas e encontros sobre o total de atividades', () => {
    const c = curso('c1', ['a1', 'a2'], ['s1', 's2']);
    const r = registro('c1', ['a1'], ['s1']);
    expect(frequenciaPercent(c, r)).toBe(50);
  });

  it('resíduo não infla a frequência que emite certificado', () => {
    // Com os órfãos contados, isto daria 100% e dispararia certificado num
    // curso em que o aluno fez 1 de 4 atividades.
    const c = curso('c1', ['a1', 'a2'], ['s1', 's2']);
    const r = registro('c1', ['a1', 'orfa-1', 'orfa-2'], ['s-removida']);
    expect(frequenciaPercent(c, r)).toBe(25);
  });

  it('curso sem atividade nenhuma é 0', () => {
    expect(frequenciaPercent(curso('c1', []), registro('c1', []))).toBe(0);
  });
});

describe('curso concluído', () => {
  it('exige todas as aulas existentes', () => {
    const c = curso('c1', ['a1', 'a2']);
    expect(cursoConcluido(c, registro('c1', ['a1']))).toBe(false);
    expect(cursoConcluido(c, registro('c1', ['a1', 'a2']))).toBe(true);
  });

  it('curso vazio não conta como concluído', () => {
    expect(cursoConcluido(curso('c1', []), registro('c1', []))).toBe(false);
  });

  it('só órfãos não concluem curso', () => {
    expect(cursoConcluido(curso('c1', ['a1']), registro('c1', ['orfa']))).toBe(false);
  });
});

describe('média de progresso', () => {
  it('reproduz o caso do Perfil e devolve valor possível', () => {
    // Dados reais que exibiam 113%: um curso de 1 aula com 2 ids órfãos (200%)
    // e outro com 1 de 4 aulas (25%) — média 112,5 -> 113.
    const cursos = [curso('foto', ['aula-nova']), curso('c2', ['b1', 'b2', 'b3', 'b4'])];
    const progresso = [
      registro('foto', ['orfa-1', 'orfa-2']),
      registro('c2', ['b1']),
    ];

    const media = mediaProgresso(cursos, progresso, 'aluno-1');
    expect(media).toBe(13); // (0 + 25) / 2
    expect(media).toBeLessThanOrEqual(100);
  });

  it('média é sobre os cursos informados, não sobre os registros', () => {
    // Registro de um curso fora da lista não entra na conta.
    const cursos = [curso('c1', ['a1', 'a2'])];
    const progresso = [registro('c1', ['a1']), registro('curso-que-saiu', ['x'])];
    expect(mediaProgresso(cursos, progresso, 'aluno-1')).toBe(50);
  });

  it('sem curso a média é 0, não NaN', () => {
    expect(mediaProgresso([], [], 'aluno-1')).toBe(0);
  });

  it('ignora progresso de outro aluno', () => {
    const cursos = [curso('c1', ['a1', 'a2'])];
    const progresso = [registro('c1', ['a1', 'a2'], [], 'outro-aluno')];
    expect(mediaProgresso(cursos, progresso, 'aluno-1')).toBe(0);
  });
});

describe('registroDoAluno', () => {
  it('casa por curso E por aluno', () => {
    const progresso = [
      registro('c1', ['a1'], [], 'outro'),
      registro('c1', ['a1', 'a2'], [], 'aluno-1'),
    ];
    expect(registroDoAluno(progresso, 'c1', 'aluno-1')?.completedLessons).toEqual(['a1', 'a2']);
    expect(registroDoAluno(progresso, 'c9', 'aluno-1')).toBeUndefined();
  });
});
