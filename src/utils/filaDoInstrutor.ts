/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * A fila "A fazer" do instrutor.
 *
 * O trabalho diário — aprovar matrícula, corrigir exercício — estava espalhado
 * dentro de "Gestão do Curso" e "Avaliações", **sem contagem em lugar nenhum**.
 * Para saber se havia algo a fazer, era preciso entrar em cada aba e olhar.
 *
 * Duas decisões que valem registrar:
 *
 * 1. **São dois itens, e não três.** O handoff pede "matrículas a aprovar,
 *    exercícios a corrigir, notas a lançar". As duas primeiras existem; a
 *    terceira não: avaliação neste sistema é corrigida automaticamente
 *    (`QuizSubmission` já chega com `scorePercent` e `passed`), então não há
 *    nota esperando lançamento. Um contador de trabalho inexistente é pior que
 *    contador nenhum — manda a pessoa procurar o que não existe.
 *
 * 2. **Item com zero não aparece.** Uma fila que mostra "0 exercícios a
 *    corrigir" transforma-se em ruído permanente, e a pessoa para de ler.
 *    Fila vazia é ausência de fila.
 *
 * Puro de propósito: contagem calculada dentro do JSX foi o que permitiu o
 * indicador decorativo que já vivia neste painel.
 */

/** Um item da fila, já contado. */
export interface ItemDaFila {
  id: 'matriculas' | 'exercicios';
  /** Texto no singular, para quando a quantidade é 1. */
  rotuloSingular: string;
  /** Texto no plural. */
  rotuloPlural: string;
  quantidade: number;
  /** Aba do painel que resolve a pendência. */
  aba: 'students' | 'exercicios';
}

/** O que a fila precisa saber de uma solicitação de matrícula. */
export interface SolicitacaoDeMatricula {
  status?: string;
  courseId?: string;
}

/** O que a fila precisa saber de uma entrega de exercício. */
export interface EntregaDeExercicio {
  status?: string;
  exerciseId?: string;
}

/** O que a fila precisa saber de um exercício. */
export interface ExercicioDaFila {
  id?: string;
  courseId?: string;
}

/** O que a fila precisa saber de um curso. */
export interface CursoDaFila {
  id?: string;
  instructorId?: string;
}

export interface DadosDaFila {
  admissionRequests?: SolicitacaoDeMatricula[] | null;
  exerciseSubmissions?: EntregaDeExercicio[] | null;
  exercises?: ExercicioDaFila[] | null;
  courses?: CursoDaFila[] | null;
  /** Quem está olhando. A fila é do trabalho DELE, não da escola inteira. */
  instructorId: string;
}

/**
 * Itens com pendência, na ordem em que aparecem.
 *
 * Só conta o que é do instrutor: matrícula de curso que ele conduz, e entrega
 * de exercício de curso que ele conduz. Somar a escola inteira mostraria
 * trabalho que não é dele — e a pessoa aprende a ignorar a fila.
 */
export function filaDoInstrutor(dados: DadosDaFila): ItemDaFila[] {
  const cursos = dados.courses ?? [];
  const meusCursos = new Set(
    cursos.filter((c) => c.instructorId === dados.instructorId).map((c) => c.id)
  );

  const matriculas = (dados.admissionRequests ?? []).filter(
    (r) => r.status === 'pending' && r.courseId !== undefined && meusCursos.has(r.courseId)
  ).length;

  const meusExercicios = new Set(
    (dados.exercises ?? [])
      .filter((e) => e.courseId !== undefined && meusCursos.has(e.courseId))
      .map((e) => e.id)
  );
  const exercicios = (dados.exerciseSubmissions ?? []).filter(
    (s) => s.status === 'pending' && s.exerciseId !== undefined && meusExercicios.has(s.exerciseId)
  ).length;

  const itens: ItemDaFila[] = [];

  if (matriculas > 0) {
    itens.push({
      id: 'matriculas',
      rotuloSingular: 'matrícula aguardando aprovação',
      rotuloPlural: 'matrículas aguardando aprovação',
      quantidade: matriculas,
      aba: 'students',
    });
  }

  if (exercicios > 0) {
    itens.push({
      id: 'exercicios',
      rotuloSingular: 'exercício aguardando correção',
      rotuloPlural: 'exercícios aguardando correção',
      quantidade: exercicios,
      aba: 'exercicios',
    });
  }

  return itens;
}

/** O texto de um item, com a concordância certa. */
export function textoDoItem(item: ItemDaFila): string {
  const rotulo = item.quantidade === 1 ? item.rotuloSingular : item.rotuloPlural;

  return `${item.quantidade} ${rotulo}`;
}
