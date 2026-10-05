import { describe, it, expect } from 'vitest';
import {
  situacaoDe,
  rotuloSituacao,
  aceitaEntrega,
  parseDataBr,
  prazoDe,
  progressoDoAluno,
  filaDeCorrecao,
  exerciciosDoCurso,
} from '../../src/utils/exerciseStatus';
import { PracticalExercise, ExerciseSubmission } from '../../src/types';

const ex = (id: string, over: Partial<PracticalExercise> = {}): PracticalExercise => ({
  id,
  courseId: 'course-1',
  title: `Exercício ${id}`,
  description: 'd',
  instructions: 'i',
  maxPoints: 100,
  ...over,
});

const sub = (
  exerciseId: string,
  status: ExerciseSubmission['status'],
  over: Partial<ExerciseSubmission> = {}
): ExerciseSubmission => ({
  id: `sub-${exerciseId}`,
  exerciseId,
  userId: 'aluno-1',
  studentName: 'João Silva',
  submissionText: 'texto',
  submittedAt: '01/09/2026 10:00:00',
  status,
  ...over,
});

describe('situação da entrega', () => {
  it('sem entrega é "não entregue" — estado que não existe no banco', () => {
    expect(situacaoDe(undefined)).toBe('nao_entregue');
    expect(rotuloSituacao(undefined, 100)).toEqual({
      situacao: 'nao_entregue',
      texto: 'Não entregue',
      tom: 'neutro',
    });
  });

  it('aprovado mostra a nota, que é o que o aluno abriu a página para ver', () => {
    const r = rotuloSituacao(sub('e1', 'approved', { score: 95 }), 100);
    expect(r.texto).toBe('Nota 95 de 100');
    expect(r.tom).toBe('ok');
  });

  it('aprovado sem score não quebra a tela nem inventa nota', () => {
    expect(rotuloSituacao(sub('e1', 'approved'), 100).texto).toBe('Nota 0 de 100');
  });

  it('pending, revision e rejected pedem ações diferentes', () => {
    expect(rotuloSituacao(sub('e1', 'pending'), 100).texto).toBe('Aguardando correção');
    expect(rotuloSituacao(sub('e1', 'revision'), 100).texto).toBe('Ajustes solicitados');
    expect(rotuloSituacao(sub('e1', 'rejected'), 100).texto).toBe('Refazer entrega');
  });

  it('só entrega aprovada fecha o envio', () => {
    expect(aceitaEntrega(undefined)).toBe(true);
    expect(aceitaEntrega(sub('e1', 'pending'))).toBe(true);
    expect(aceitaEntrega(sub('e1', 'revision'))).toBe(true);
    expect(aceitaEntrega(sub('e1', 'rejected'))).toBe(true);
    expect(aceitaEntrega(sub('e1', 'approved'))).toBe(false);
  });
});

describe('parseDataBr', () => {
  it('entende dd/mm/aaaa', () => {
    const d = parseDataBr('15/07/2026');
    expect(d?.getFullYear()).toBe(2026);
    expect(d?.getMonth()).toBe(6);
    expect(d?.getDate()).toBe(15);
  });

  it('recusa data inexistente em vez de rolar o mês', () => {
    // new Date(2026, 1, 31) viraria 03/03 — e o card mostraria um prazo que
    // ninguém digitou.
    expect(parseDataBr('31/02/2026')).toBeNull();
  });

  it('recusa texto livre e formatos que não são dd/mm/aaaa', () => {
    expect(parseDataBr('15 de julho')).toBeNull();
    expect(parseDataBr('2026-07-15')).toBeNull();
    expect(parseDataBr('')).toBeNull();
    expect(parseDataBr(undefined)).toBeNull();
  });
});

describe('prazo', () => {
  const agora = new Date(2026, 8, 2, 15, 30); // 02/09/2026, meio da tarde

  it('sem prazo não é prazo nenhum', () => {
    expect(prazoDe(undefined, agora)).toBeNull();
    expect(prazoDe('   ', agora)).toBeNull();
  });

  it('compara por dia, não por instante', () => {
    // Mesmo dia, hora já passada: ainda é "vence hoje", não "atrasado".
    expect(prazoDe('02/09/2026', agora)).toEqual({
      texto: 'Vence hoje',
      atrasado: false,
      reconhecido: true,
    });
  });

  it('amanhã, futuro e passado têm textos próprios', () => {
    expect(prazoDe('03/09/2026', agora).texto).toBe('Vence amanhã');
    expect(prazoDe('07/09/2026', agora).texto).toBe('Vence em 5 dias');
    expect(prazoDe('01/09/2026', agora).texto).toBe('Atrasado há 1 dia');
    expect(prazoDe('10/07/2026', agora).texto).toBe('Atrasado há 54 dias');
  });

  it('marca atraso só no passado', () => {
    expect(prazoDe('01/09/2026', agora).atrasado).toBe(true);
    expect(prazoDe('03/09/2026', agora).atrasado).toBe(false);
  });

  it('data que não sabe ler é exibida crua e NÃO afirma atraso', () => {
    // `dueDate` é texto livre no banco. Dizer "atrasado" a partir de algo
    // ilegível é pior que não dizer nada.
    expect(prazoDe('julho de 2026', agora)).toEqual({
      texto: 'Prazo: julho de 2026',
      atrasado: false,
      reconhecido: false,
    });
  });
});

describe('progresso do aluno', () => {
  const exercicios = [ex('e1'), ex('e2'), ex('e3', { maxPoints: 50 })];

  it('conta entregas e corrigidos separadamente', () => {
    const p = progressoDoAluno(
      exercicios,
      [sub('e1', 'approved', { score: 80 }), sub('e2', 'pending')],
      'aluno-1'
    );
    expect(p).toEqual({ total: 3, entregues: 2, corrigidos: 1, pontos: 80, pontosPossiveis: 100 });
  });

  it('pontos possíveis somam só o já corrigido', () => {
    // Com 3 exercícios de 100+100+50 e um único corrigido, "80 de 100" é a
    // leitura honesta; "80 de 250" puniria o aluno pela fila do professor.
    const p = progressoDoAluno(exercicios, [sub('e1', 'approved', { score: 80 })], 'aluno-1');
    expect(p.pontosPossiveis).toBe(100);
  });

  it('ignora entrega de outro aluno', () => {
    const p = progressoDoAluno(
      exercicios,
      [sub('e1', 'approved', { score: 100, userId: 'outro' })],
      'aluno-1'
    );
    expect(p.entregues).toBe(0);
    expect(p.pontos).toBe(0);
  });

  it('rejeitado e em revisão contam como entregue, não como corrigido', () => {
    const p = progressoDoAluno(
      exercicios,
      [sub('e1', 'rejected'), sub('e2', 'revision')],
      'aluno-1'
    );
    expect(p.entregues).toBe(2);
    expect(p.corrigidos).toBe(0);
  });
});

describe('fila de correção', () => {
  it('conta só as entregas dos exercícios informados', () => {
    const fila = filaDeCorrecao(
      [ex('e1'), ex('e2')],
      [sub('e1', 'pending'), sub('e2', 'approved'), sub('e9', 'pending')]
    );
    // sub de 'e9' é de outro conjunto: não entra na fila deste professor.
    expect(fila).toEqual({ aguardando: 1, corrigidas: 1, total: 2 });
  });

  it('sem entregas a fila é zero, não indefinida', () => {
    expect(filaDeCorrecao([ex('e1')], [])).toEqual({ aguardando: 0, corrigidas: 0, total: 0 });
  });
});

describe('exercícios do curso', () => {
  it('filtra pelo curso e ordena por vencimento', () => {
    const lista = exerciciosDoCurso(
      [
        ex('e1', { dueDate: '20/09/2026' }),
        ex('e2', { dueDate: '05/09/2026' }),
        ex('e3', { courseId: 'course-2', dueDate: '01/09/2026' }),
      ],
      'course-1'
    );
    expect(lista.map((e) => e.id)).toEqual(['e2', 'e1']);
  });

  it('sem prazo vai para o fim, não para o começo', () => {
    const lista = exerciciosDoCurso(
      [ex('e1'), ex('e2', { dueDate: '05/09/2026' }), ex('e3', { dueDate: 'quando der' })],
      'course-1'
    );
    expect(lista.map((e) => e.id)).toEqual(['e2', 'e1', 'e3']);
  });

  it('não altera o array recebido', () => {
    const original = [ex('e1', { dueDate: '20/09/2026' }), ex('e2', { dueDate: '05/09/2026' })];
    exerciciosDoCurso(original, 'course-1');
    expect(original.map((e) => e.id)).toEqual(['e1', 'e2']);
  });
});

describe('bloco de exercícios: mostrar só quando existe', () => {
  const ex = (id: string, courseId: string): PracticalExercise => ({
    id,
    courseId,
    title: `Exercício ${id}`,
    description: 'd',
    instructions: 'i',
    maxPoints: 100,
  } as PracticalExercise);

  it('disciplina sem exercício devolve lista vazia — o bloco não deve aparecer', () => {
    // A regra é do produto: caixa com título e "Nenhum exercício prático
    // lançado neste curso" ocupava a coluna para dizer que não há nada a fazer.
    expect(exerciciosDoCurso([], 'curso-sem')).toEqual([]);
    expect(exerciciosDoCurso([ex('e1', 'outro-curso')], 'curso-sem')).toEqual([]);
  });

  it('exercício de outra disciplina não faz o bloco aparecer', () => {
    const lista = exerciciosDoCurso([ex('e1', 'curso-a'), ex('e2', 'curso-b')], 'curso-b');
    expect(lista.map((e) => e.id)).toEqual(['e2']);
  });

  it('com exercício da própria disciplina, a lista não é vazia', () => {
    expect(exerciciosDoCurso([ex('e1', 'curso-a')], 'curso-a')).toHaveLength(1);
  });
});
