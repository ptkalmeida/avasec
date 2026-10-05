/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Contas de progresso e frequência de um aluno num curso.
 *
 * Existe por causa de um defeito real: `StudentProgress.completedLessons` guarda
 * ids de aula, e quando uma aula é APAGADA do curso o id continua ali. A conta
 * `completedLessons.length / course.lessons.length` passava a dividir 2 por 1 e
 * o Perfil exibia "113% de progresso médio" — um curso com 1 aula e 2 ids de
 * aulas que não existem mais dava 200%.
 *
 * Regra aqui: só conta o que AINDA EXISTE no curso. Id órfão não é progresso;
 * é resíduo. Além de mentir na tela, ele inflava a frequência que dispara a
 * emissão automática de certificado.
 */

import { Course, StudentProgress } from '../types';

/** Registro de progresso do aluno naquele curso, se houver. */
export const registroDoAluno = (
  progress: StudentProgress[],
  courseId: string,
  userId: string
): StudentProgress | undefined =>
  progress.find((p) => p.courseId === courseId && p.userId === userId);

/** Aulas concluídas que ainda existem no curso. */
export const aulasConcluidas = (course: Course, registro?: StudentProgress): number => {
  if (!registro) return 0;
  const existentes = new Set(course.lessons.map((l) => l.id));

  return registro.completedLessons.filter((id) => existentes.has(id)).length;
};

/** Encontros ao vivo com presença que ainda existem no curso. */
export const presencasAoVivo = (course: Course, registro?: StudentProgress): number => {
  if (!registro) return 0;
  const existentes = new Set((course.liveSessions ?? []).map((s) => s.id));

  return registro.attendedLiveSessions.filter((id) => existentes.has(id)).length;
};

/** Percentual de aulas concluídas, de 0 a 100. */
export const progressoPercent = (course: Course, registro?: StudentProgress): number => {
  const total = course.lessons.length;
  if (total === 0) return 0;

  return Math.min(100, Math.round((aulasConcluidas(course, registro) / total) * 100));
};

/**
 * Frequência: aulas concluídas + presenças ao vivo sobre o total de atividades.
 * É o número que decide a emissão de certificado, então filtrar órfão aqui não
 * é cosmético.
 */
export const frequenciaPercent = (course: Course, registro?: StudentProgress): number => {
  const totalAtividades = course.lessons.length + (course.liveSessions?.length ?? 0);
  if (totalAtividades === 0) return 0;

  const feitas = aulasConcluidas(course, registro) + presencasAoVivo(course, registro);

  return Math.min(100, Math.round((feitas / totalAtividades) * 100));
};

/** O curso foi inteiramente concluído (todas as aulas que existem hoje). */
export const cursoConcluido = (course: Course, registro?: StudentProgress): boolean =>
  course.lessons.length > 0 && aulasConcluidas(course, registro) === course.lessons.length;

/**
 * Média de progresso do aluno nos cursos informados.
 *
 * A média é sobre os CURSOS passados, não sobre os registros de progresso: um
 * registro de curso que saiu do catálogo (ou de que o aluno não é mais aluno)
 * não deve entrar na média — e antes entrava, porque a conta iterava os
 * registros do aluno e ignorava o curso quando não o encontrava, deixando o
 * divisor maior que o numerador.
 */
export const mediaProgresso = (
  cursos: Course[],
  progress: StudentProgress[],
  userId: string
): number => {
  if (cursos.length === 0) return 0;

  const soma = cursos.reduce(
    (acc, curso) => acc + progressoPercent(curso, registroDoAluno(progress, curso.id, userId)),
    0
  );

  return Math.round(soma / cursos.length);
};
