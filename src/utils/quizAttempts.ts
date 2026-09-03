/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Histórico de tentativas de avaliação — qual é a vigente e em que ordem.
 *
 * Enquanto responder de novo inativava a tentativa anterior, a lista tinha uma
 * linha por aluno+quiz e qualquer `find()` acertava. Com o histórico no ar,
 * "a nota do aluno" passou a ser uma ESCOLHA, e escolher errado numa tela de
 * nota é problema acadêmico: há consumidor que lê a primeira linha e a imprime
 * numa declaração.
 *
 * A ordenação usa `enviadoEm`, a coluna criada para isso. `submittedAt` é texto
 * de exibição ('03/09/2026 às 16:23') e ordena alfabeticamente — '01/12' antes
 * de '03/09'. Ele continua sendo o que a tela mostra, nunca o que ela compara.
 */

import { QuizSubmission } from '../types';

/**
 * Instante da tentativa otimista, na mesma escala do que o servidor devolve.
 *
 * `toISOString()` de propósito: o valor carrega o fuso ('...Z'), e o servidor
 * também ('...+00:00'). A aplicação roda em UTC e o navegador do aluno não —
 * gravar aqui a hora do relógio local, sem sufixo, faria a tentativa acabada de
 * enviar parecer três horas mais ANTIGA que as anteriores, e a página exibiria a
 * tentativa errada como vigente.
 */
export const momentoIso = (data: Date): string => data.toISOString();

/**
 * Instante da tentativa em milissegundos, ou null quando não há como saber.
 *
 * Cai para `submittedAt` porque tentativa gravada antes da coluna existir pode
 * ter ficado sem ela — e a string, quando está na forma conhecida, é informação
 * boa. `new Date(texto)` NÃO serve: '03/09/2026' seria lido como o padrão
 * americano e 3 de setembro viraria 9 de março.
 *
 * Esse desvio é aproximado: a string não diz o fuso em que foi formatada, e a
 * aplicação a gera em UTC. Serve para ordenar linha antiga entre linhas antigas,
 * não para cravar o instante — por isso `enviadoEm` é sempre preferido.
 */
export const momentoDaTentativa = (sub: Pick<QuizSubmission, 'enviadoEm' | 'submittedAt'>): number | null => {
  if (typeof sub.enviadoEm === 'string' && sub.enviadoEm.trim() !== '') {
    const t = new Date(sub.enviadoEm).getTime();
    if (!Number.isNaN(t)) return t;
  }

  const m = /^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(?:às\s+)?(\d{2}):(\d{2}))?/.exec((sub.submittedAt ?? '').trim());
  if (m === null) return null;

  const [, dia, mes, ano, hora, minuto] = m;
  const data = new Date(Number(ano), Number(mes) - 1, Number(dia), Number(hora ?? 0), Number(minuto ?? 0));

  return Number.isNaN(data.getTime()) ? null : data.getTime();
};

/**
 * Data e hora da tentativa, para ler na tela.
 *
 * Prefere `enviadoEm` porque `submittedAt` é formatado NO SERVIDOR, que roda em
 * UTC: uma tentativa enviada às 17:08 de Brasília era gravada — e exibida — como
 * "20:08". `enviadoEm` carrega o fuso, então aqui a hora sai no relógio de quem
 * está lendo.
 *
 * Isto não reescreve o que já está no banco. Tentativa antiga sem `enviadoEm`
 * cai na string como ela está: melhor a hora deslocada, que é o que sempre se
 * viu ali, do que uma correção adivinhada em cima de dado que não diz seu fuso.
 */
export const textoDaTentativa = (sub: Pick<QuizSubmission, 'enviadoEm' | 'submittedAt'>): string => {
  if (typeof sub.enviadoEm === 'string' && sub.enviadoEm.trim() !== '') {
    const d = new Date(sub.enviadoEm);
    if (!Number.isNaN(d.getTime())) {
      return `${d.toLocaleDateString('pt-BR')} às ${d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
    }
  }

  return sub.submittedAt;
};

/**
 * Da mais recente para a mais antiga. Tentativa sem data reconhecível vai para
 * o fim — sem data, o lugar dela é onde a mais antiga estaria, e nenhuma data é
 * inventada para preencher a lacuna.
 *
 * `sort` do JavaScript é estável desde o ES2019, então tentativas empatadas
 * mantêm a ordem em que chegaram (a API já as entrega da vigente para trás).
 */
export const ordenarTentativas = <T extends Pick<QuizSubmission, 'enviadoEm' | 'submittedAt'>>(
  subs: readonly T[]
): T[] =>
  [...subs].sort((a, b) => {
    const ma = momentoDaTentativa(a);
    const mb = momentoDaTentativa(b);
    if (ma === mb) return 0;
    if (ma === null) return 1;
    if (mb === null) return -1;

    return mb - ma;
  });

/** Tentativas de um aluno num quiz, da mais recente para a mais antiga. */
export const tentativasDe = (
  subs: readonly QuizSubmission[],
  quizId: string,
  userId: string
): QuizSubmission[] =>
  ordenarTentativas(subs.filter((s) => s.quizId === quizId && s.userId === userId));

/**
 * A tentativa que vale: a mais recente.
 *
 * Não é a de maior nota. Refazer uma avaliação substitui o resultado anterior —
 * é assim que a tela sempre se comportou ("Refazer Avaliação"), e escolher a
 * melhor nota mudaria a regra acadêmica sem ninguém ter decidido isso.
 */
export const tentativaVigente = (
  subs: readonly QuizSubmission[],
  quizId: string,
  userId: string
): QuizSubmission | undefined => tentativasDe(subs, quizId, userId)[0];

/** Quantas tentativas antes da vigente. Zero quando só houve uma. */
export const tentativasAnteriores = (
  subs: readonly QuizSubmission[],
  quizId: string,
  userId: string
): QuizSubmission[] => tentativasDe(subs, quizId, userId).slice(1);

/** Chave de agrupamento de tentativas: um aluno, uma avaliação. */
const chave = (sub: QuizSubmission): string => `${sub.userId}::${sub.quizId}`;

/**
 * Só as tentativas vigentes — uma por aluno+avaliação.
 *
 * É o que qualquer média precisa receber. Somar a lista crua passou a significar
 * "média entre tentativas": um aluno que refaz três vezes pesa o triplo de quem
 * fez uma, e um erro corrigido na segunda tentativa continua arrastando a média
 * para baixo para sempre.
 */
export const apenasVigentes = (subs: readonly QuizSubmission[]): QuizSubmission[] => {
  const porAlunoEQuiz = new Map<string, QuizSubmission>();
  for (const sub of ordenarTentativas(subs)) {
    if (!porAlunoEQuiz.has(chave(sub))) porAlunoEQuiz.set(chave(sub), sub);
  }

  return [...porAlunoEQuiz.values()];
};

/** Média das notas vigentes, ou null quando não há tentativa nenhuma. */
export const mediaDasVigentes = (subs: readonly QuizSubmission[]): number | null => {
  const vigentes = apenasVigentes(subs);
  if (vigentes.length === 0) return null;

  return Math.round(vigentes.reduce((soma, s) => soma + s.scorePercent, 0) / vigentes.length);
};

/**
 * Média de um aluno num curso, considerando só a tentativa vigente de cada
 * avaliação. É o número que sai na declaração impressa.
 */
export const mediaDoAlunoNoCurso = (
  subs: readonly QuizSubmission[],
  userId: string,
  courseId: string
): number | null =>
  mediaDasVigentes(subs.filter((s) => s.userId === userId && s.courseId === courseId));
