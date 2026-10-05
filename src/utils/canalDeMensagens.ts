/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * O canal de mensagens está aberto para esta pessoa?
 *
 * Existe porque o sino do cabeçalho e a aba de mensagens liam condições
 * DIFERENTES, e por isso discordavam:
 *
 * - o sino (`App.tsx`) exigia só `features.mensagensDiretas`;
 * - a aba do instrutor exige `forum && mensagensDiretas && allowDirectMessages`;
 * - a aba do aluno exige o mesmo trio (ver `abasAluno`).
 *
 * Com `forum: false` — que é a configuração de hoje — o resultado era um sino
 * ativo, piscando, levando a uma aba que não existe. Pior: ele tentava rolar
 * até `#chat-portal-section`, elemento que só o painel do ALUNO renderiza, de
 * modo que para o instrutor o clique não fazia nada visível.
 *
 * Uma função, um lugar. Quando a condição mudar, ela muda para os dois.
 */

/** O que este módulo precisa saber das flags de produto. */
export interface FlagsDeMensagens {
  forum?: boolean;
  mensagensDiretas?: boolean;
}

/** O que este módulo precisa saber das configurações do administrador. */
export interface AjustesDeMensagens {
  allowDirectMessages?: boolean;
}

/** Papéis que têm canal de mensagens. O administrador não lê conversa (privacidade). */
export type PapelComCanal = 'student' | 'instructor';

/**
 * `true` quando a pessoa realmente tem para onde ir ao clicar no sino.
 *
 * `allowDirectMessages` é chave **editável pelo administrador**: ela entra aqui
 * para os dois papéis, porque desligar mensagens no sistema tem de desligar
 * também o aviso de mensagem nova — um sino que pisca sem destino é pior que
 * nenhum sino.
 */
export function canalDeMensagensAberto(
  papel: string | null | undefined,
  features: FlagsDeMensagens | null | undefined,
  systemSettings: AjustesDeMensagens | null | undefined
): boolean {
  if (papel !== 'student' && papel !== 'instructor') return false;

  const f = features ?? {};
  const s = systemSettings ?? {};

  return f.forum === true && f.mensagensDiretas === true && s.allowDirectMessages === true;
}
