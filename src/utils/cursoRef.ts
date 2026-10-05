/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * A referência de curso que viaja no endereço.
 *
 * O endereço era `/aluno/curso/course-1` e não dizia de que curso se tratava —
 * e o endereço é justamente a parte da tela que a pessoa copia, salva e manda
 * para outra. Passa a ser `/aluno/curso/ux-ui-design-interfaces-de-alta-performance`.
 *
 * **Slug e id não são a mesma coisa, e a diferença importa aqui.** O slug é o
 * endereço; o id é o que a API entende. Toda chamada continua indo com o id — o
 * slug morre nesta camada. Confundir os dois daria requisição com slug onde o
 * servidor espera id, e o erro apareceria longe da causa.
 *
 * Aceitar id também na leitura não é indulgência: todo link que circula hoje
 * tem a forma antiga, e uma melhoria de endereço que transforma link salvo em
 * 404 é uma regressão disfarçada de melhoria. Slug aposentado (curso renomeado)
 * não é resolvido aqui — depende do histórico no banco, e é `/api/courses/resolve`
 * quem responde.
 */

/** O mínimo que este módulo precisa saber de um curso. */
export interface CursoEnderecavel {
  id: string;
  slug?: string;
}

/** Como o curso aparece no endereço. Cai no id quando não há slug. */
export function refDoCurso(curso: CursoEnderecavel | null | undefined): string {
  if (curso === null || curso === undefined) return '';

  const slug = typeof curso.slug === 'string' ? curso.slug.trim() : '';

  return slug === '' ? curso.id : slug;
}

/**
 * Curso a partir do que veio no endereço: slug primeiro, id como reserva.
 *
 * O slug vem primeiro de propósito. `CursoSlug` (backend) garante que nenhum
 * slug tenha a forma de um id de curso, então as duas buscas não podem apontar
 * para cursos diferentes — mas a ordem deixa a intenção explícita para quem ler
 * depois, em vez de depender da garantia estar do outro lado.
 */
export function cursoPorRef<T extends CursoEnderecavel>(
  cursos: readonly T[] | null | undefined,
  ref: string | null | undefined
): T | null {
  if (typeof ref !== 'string' || ref === '') return null;

  const lista = cursos ?? [];

  return lista.find((c) => typeof c.slug === 'string' && c.slug === ref)
    ?? lista.find((c) => c.id === ref)
    ?? null;
}

/**
 * A referência recebida JÁ é o endereço de hoje?
 *
 * Quando é false, quem chama troca a URL da barra pela canônica. Sem isso o
 * endereço antigo se propaga: a pessoa abre por um link com id, copia da barra,
 * manda para outra, e o `course-1` sobrevive à mudança que veio removê-lo.
 */
export function refEhCanonica(curso: CursoEnderecavel | null | undefined, ref: string | null | undefined): boolean {
  if (curso === null || curso === undefined) return true;
  if (typeof ref !== 'string' || ref === '') return true;

  return refDoCurso(curso) === ref;
}
