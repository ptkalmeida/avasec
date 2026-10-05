/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Quando o certificado pode sair.
 *
 * A regra é do servidor — `CertificateService::issueCertificate` recalcula tudo
 * e é ele quem emite ou recusa. Este arquivo existe para a TELA não mentir: sem
 * ele, o painel anunciava "certificado disponível" com base só na frequência, e
 * o aluno clicava para receber um 403.
 *
 * O que se protege aqui é o motivo da regra existir: desde que concluir aula
 * passou a ser automático ao avançar, quem clicasse "Próxima aula" até o fim
 * batia o mínimo de frequência e **o certificado saía sem uma única questão
 * respondida**. Certificado de escola pública é registro acadêmico, e revogar
 * depois é ato administrativo, não um desfazer.
 *
 * Decisão da coordenação (09/09/2026): curso com avaliação exige TODAS as
 * avaliações ativas APROVADAS; curso sem avaliação segue só pela frequência —
 * é o que ele mede.
 *
 * Duplicação consciente com o backend: são duas perguntas diferentes. Lá é
 * "posso emitir?"; aqui é "o que digo à pessoa antes de ela clicar?". Se as duas
 * divergirem, quem manda é o servidor, e a tela mostra o erro dele.
 */

interface QuizMinimo {
  id: string;
  courseId: string;
  title: string;
}

interface SubmissaoMinima {
  userId: string;
  courseId: string;
  quizId: string;
  passed: boolean;
}

/**
 * Títulos das avaliações do curso em que o aluno ainda não foi aprovado.
 *
 * A lista de avaliações que chega ao aluno já vem filtrada pela escada de
 * visibilidade do servidor — prova em rascunho não aparece aqui e portanto não
 * é cobrada, o mesmo recorte que o backend aplica.
 *
 * Aprovação vale de QUALQUER tentativa, não da última: a ADR 12 manda
 * acrescentar tentativa em vez de sobrescrever, então reprovar depois de já ter
 * passado não desfaz a aprovação.
 */
export function avaliacoesPendentes(
  quizzes: readonly QuizMinimo[] | null | undefined,
  submissoes: readonly SubmissaoMinima[] | null | undefined,
  courseId: string,
  userId: string
): string[] {
  const doCurso = (quizzes ?? []).filter((q) => q.courseId === courseId);
  if (doCurso.length === 0) return [];

  const aprovadas = new Set(
    (submissoes ?? [])
      .filter((s) => s.passed === true && s.userId === userId && s.courseId === courseId)
      .map((s) => s.quizId)
  );

  return doCurso.filter((q) => !aprovadas.has(q.id)).map((q) => q.title);
}

/** O aluno cumpriu tudo o que o curso exige para o certificado? */
export function podeReceberCertificado(params: {
  frequencia: number;
  frequenciaMinima: number;
  pendentes: readonly string[];
}): boolean {
  return params.frequencia >= params.frequenciaMinima && params.pendentes.length === 0;
}

/**
 * O que falta, em uma frase, para quem está olhando a tela.
 *
 * Devolve null quando não falta nada — quem chama usa isso para decidir entre
 * mostrar o certificado e mostrar a pendência, sem repetir a condição.
 */
export function oQueFaltaParaOCertificado(params: {
  frequencia: number;
  frequenciaMinima: number;
  pendentes: readonly string[];
}): string | null {
  const { frequencia, frequenciaMinima, pendentes } = params;
  const faltaFrequencia = frequencia < frequenciaMinima;

  if (!faltaFrequencia && pendentes.length === 0) return null;

  if (faltaFrequencia && pendentes.length === 0) {
    return `Frequência de ${frequenciaMinima}% exigida — você tem ${frequencia}%.`;
  }

  const avaliacoes = pendentes.length === 1
    ? `ser aprovado na avaliação "${pendentes[0]}"`
    : `ser aprovado em ${pendentes.length} avaliações: ${pendentes.join(', ')}`;

  return faltaFrequencia
    ? `Falta ${avaliacoes}, e a frequência de ${frequenciaMinima}% (você tem ${frequencia}%).`
    : `Falta ${avaliacoes}.`;
}
