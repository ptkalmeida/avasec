/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Regras de leitura dos exercícios práticos — o que a tela mostra sobre prazo,
 * situação da entrega e progresso.
 *
 * Fica fora dos componentes porque é a parte que erra em silêncio: "atrasado",
 * "falta 1 dia" e "3 de 4 corrigidos" são contas, e conta errada numa tela de
 * nota é problema acadêmico, não cosmético. Aqui dá para testar.
 */

import { PracticalExercise, ExerciseSubmission } from '../types';

/** Situação de UM exercício para UM aluno. `nao_entregue` não existe no banco. */
export type SituacaoEntrega = 'nao_entregue' | 'pending' | 'approved' | 'rejected' | 'revision';

/** Peso visual, para o componente escolher a cor sem repetir a regra. */
export type Tom = 'ok' | 'aguardando' | 'atencao' | 'neutro';

export interface RotuloSituacao {
  situacao: SituacaoEntrega;
  texto: string;
  tom: Tom;
}

export const situacaoDe = (submissao?: ExerciseSubmission): SituacaoEntrega =>
  submissao === undefined ? 'nao_entregue' : submissao.status;

/**
 * Rótulo que o aluno lê. Aprovado mostra a nota — é a informação que a pessoa
 * abriu a página para ver; o resto mostra o que se espera dela.
 */
export const rotuloSituacao = (
  submissao: ExerciseSubmission | undefined,
  maxPoints: number
): RotuloSituacao => {
  const situacao = situacaoDe(submissao);

  switch (situacao) {
    case 'approved':
      return { situacao, texto: `Nota ${submissao?.score ?? 0} de ${maxPoints}`, tom: 'ok' };
    case 'pending':
      return { situacao, texto: 'Aguardando correção', tom: 'aguardando' };
    case 'revision':
      return { situacao, texto: 'Ajustes solicitados', tom: 'atencao' };
    case 'rejected':
      return { situacao, texto: 'Refazer entrega', tom: 'atencao' };
    default:
      return { situacao, texto: 'Não entregue', tom: 'neutro' };
  }
};

/** Entrega ainda pode ser feita ou refeita? Aprovada, não. */
export const aceitaEntrega = (submissao?: ExerciseSubmission): boolean =>
  situacaoDe(submissao) !== 'approved';

export interface Prazo {
  /** Texto curto para o card: "vence hoje", "atrasado há 3 dias", "em 5 dias". */
  texto: string;
  /** Passou da data. Só é confiável quando `reconhecido` é true. */
  atrasado: boolean;
  /**
   * A data foi entendida como dd/mm/aaaa. Quando false, o valor cru é exibido e
   * NENHUMA urgência é afirmada: `dueDate` é texto livre no banco, e inventar
   * "atrasado" a partir de algo que não se sabe ler é pior que não dizer nada.
   */
  reconhecido: boolean;
}

/** Converte dd/mm/aaaa em Date local à meia-noite, ou null se não for essa forma. */
export const parseDataBr = (valor: string | undefined): Date | null => {
  if (typeof valor !== 'string') return null;
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(valor.trim());
  if (m === null) return null;

  const [, dia, mes, ano] = m;
  const data = new Date(Number(ano), Number(mes) - 1, Number(dia));
  // Rejeita 31/02: o Date rola para março e mudaria o dia informado.
  if (data.getDate() !== Number(dia) || data.getMonth() !== Number(mes) - 1) return null;

  return data;
};

/** Dias inteiros entre duas datas, comparando por dia e não por instante. */
const diasEntre = (de: Date, para: Date): number => {
  const a = new Date(de.getFullYear(), de.getMonth(), de.getDate()).getTime();
  const b = new Date(para.getFullYear(), para.getMonth(), para.getDate()).getTime();

  return Math.round((b - a) / 86400000);
};

export const prazoDe = (dueDate: string | undefined, agora: Date): Prazo | null => {
  if (dueDate === undefined || dueDate.trim() === '') return null;

  const data = parseDataBr(dueDate);
  if (data === null) {
    return { texto: `Prazo: ${dueDate.trim()}`, atrasado: false, reconhecido: false };
  }

  const dias = diasEntre(agora, data);
  if (dias === 0) return { texto: 'Vence hoje', atrasado: false, reconhecido: true };
  if (dias === 1) return { texto: 'Vence amanhã', atrasado: false, reconhecido: true };
  if (dias > 1) return { texto: `Vence em ${dias} dias`, atrasado: false, reconhecido: true };

  const atraso = Math.abs(dias);

  return {
    texto: atraso === 1 ? 'Atrasado há 1 dia' : `Atrasado há ${atraso} dias`,
    atrasado: true,
    reconhecido: true,
  };
};

export interface ProgressoExercicios {
  total: number;
  entregues: number;
  corrigidos: number;
  /** Pontos obtidos nos exercícios JÁ corrigidos. */
  pontos: number;
  /** Pontos possíveis considerando só o que já foi corrigido — comparável a `pontos`. */
  pontosPossiveis: number;
}

/**
 * Progresso de um aluno num conjunto de exercícios.
 *
 * `pontosPossiveis` conta apenas o que já foi corrigido de propósito: somar o
 * total do curso faria a nota parecer baixa só porque o professor ainda não
 * corrigiu — "70 de 300" com duas tarefas na fila desanima sem motivo.
 */
export const progressoDoAluno = (
  exercicios: PracticalExercise[],
  submissoes: ExerciseSubmission[],
  userId: string
): ProgressoExercicios => {
  let entregues = 0;
  let corrigidos = 0;
  let pontos = 0;
  let pontosPossiveis = 0;

  for (const ex of exercicios) {
    const sub = submissoes.find((s) => s.exerciseId === ex.id && s.userId === userId);
    if (sub === undefined) continue;

    entregues += 1;
    if (sub.status === 'approved') {
      corrigidos += 1;
      pontos += sub.score ?? 0;
      pontosPossiveis += ex.maxPoints;
    }
  }

  return { total: exercicios.length, entregues, corrigidos, pontos, pontosPossiveis };
};

export interface FilaDeCorrecao {
  /** Entregas com status `pending`: é o número que o professor precisa ver. */
  aguardando: number;
  corrigidas: number;
  total: number;
}

/** Quanto há para corrigir num conjunto de exercícios (visão do professor). */
export const filaDeCorrecao = (
  exercicios: PracticalExercise[],
  submissoes: ExerciseSubmission[]
): FilaDeCorrecao => {
  const ids = new Set(exercicios.map((ex) => ex.id));
  const doConjunto = submissoes.filter((s) => ids.has(s.exerciseId));

  return {
    aguardando: doConjunto.filter((s) => s.status === 'pending').length,
    corrigidas: doConjunto.filter((s) => s.status !== 'pending').length,
    total: doConjunto.length,
  };
};

/** Exercícios de um curso, na ordem em que vencem (sem prazo vai para o fim). */
export const exerciciosDoCurso = (
  exercicios: PracticalExercise[],
  courseId: string
): PracticalExercise[] =>
  exercicios
    .filter((ex) => ex.courseId === courseId)
    .slice()
    .sort((a, b) => {
      const da = parseDataBr(a.dueDate);
      const db = parseDataBr(b.dueDate);
      if (da === null && db === null) return 0;
      if (da === null) return 1;
      if (db === null) return -1;

      return da.getTime() - db.getTime();
    });
