/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Aula a que uma mensagem de suporte se refere.
 *
 * O aluno pede ajuda de dentro de uma aula, e o gestor precisa saber de QUAL —
 * "não entendi essa parte" sem contexto obriga a perguntar de volta e faz a
 * dúvida esperar um turno inteiro.
 *
 * O contexto viaja no começo do próprio texto, como `[Aula: <título>] <dúvida>`,
 * porque `DirectMessage` não tem coluna para isso e acrescentar coluna é mudança
 * de schema — decisão que não se toma de passagem (`06-controle-de-mudancas.md`).
 * O preço é este arquivo: a marcação precisa ser lida de volta para virar rótulo
 * em vez de aparecer como ruído no meio da frase.
 *
 * A leitura é deliberadamente conservadora. Reconhece só o prefixo NO INÍCIO e
 * até o primeiro `]`; qualquer outra coisa é mensagem comum. Título que contenha
 * `]` faz o rótulo sair truncado — cosmético, e o texto completo continua
 * visível. O que NÃO pode acontecer é uma mensagem qualquer, com colchete no
 * meio, ser interpretada como referência a uma aula que ninguém citou.
 */

const PREFIXO = /^\[Aula:\s*([^\]]*)\]\s*/;

/** Marca o texto de uma dúvida com a aula de origem. */
export function comAssuntoDaAula(tituloDaAula: string, texto: string): string {
  const titulo = tituloDaAula.trim();

  return titulo === '' ? texto : `[Aula: ${titulo}] ${texto}`;
}

/** Mensagem separada em aula de origem (quando houver) e corpo. */
export interface AssuntoDaMensagem {
  /** Título da aula citada, ou null quando a mensagem não cita nenhuma. */
  aula: string | null;
  /** O texto sem a marcação, que é o que a pessoa realmente escreveu. */
  corpo: string;
}

export function assuntoDaMensagem(texto: string | null | undefined): AssuntoDaMensagem {
  if (typeof texto !== 'string') return { aula: null, corpo: '' };

  const achado = texto.match(PREFIXO);
  if (achado === null) return { aula: null, corpo: texto };

  const titulo = (achado[1] ?? '').trim();
  const corpo = texto.slice(achado[0].length);

  // `[Aula: ]` sem título não é contexto: some o rótulo, mantém o corpo.
  return titulo === '' ? { aula: null, corpo } : { aula: titulo, corpo };
}
