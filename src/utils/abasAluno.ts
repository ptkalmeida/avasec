/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Quais abas o painel do aluno mostra.
 *
 * Existe por causa de um defeito que estava no ar: a barra de abas INTEIRA vivia
 * dentro de `{features.mensagensDiretas && systemSettings.allowDirectMessages && (`.
 * Como `allowDirectMessages` é chave **editável pelo administrador** nas
 * Configurações do Sistema, desligar mensagens fazia o aluno perder de uma vez
 * Documentos, Biblioteca Digital, Eventos, Central de Ajuda e Meu Perfil — sem
 * erro na tela e sem relação nenhuma com o que foi desligado.
 *
 * A condição de cada aba passa a viver aqui, em função pura, porque foi
 * exatamente a mistura entre "condição de uma aba" e "condição da barra" que
 * causou o problema — e num JSX de cem linhas essa mistura não salta aos olhos.
 * Aqui salta, e o teste cai se voltar.
 *
 * `general` (Meu Painel de Estudos) e `faq` não têm flag: são a entrada e a
 * ajuda. Aba sem flag é decisão, não esquecimento — e está escrito.
 */

import { DashboardTab } from '../context/LMSContext';

/** O que este módulo precisa saber das flags de produto. */
export interface FlagsDoAluno {
  solicitacoesAcademicas?: boolean;
  forum?: boolean;
  mensagensDiretas?: boolean;
  materiaisComplementares?: boolean;
  eventosWebinars?: boolean;
  perfilBasico?: boolean;
}

/** O que este módulo precisa saber das configurações do administrador. */
export interface AjustesDoSistema {
  allowDirectMessages?: boolean;
}

/**
 * Abas visíveis, na ordem em que aparecem na barra.
 *
 * Mensagens é a única que depende de DUAS coisas: a flag de produto (`forum` e
 * `mensagensDiretas`) e a chave do administrador (`allowDirectMessages`). As
 * outras dependem apenas da própria flag — e nenhuma depende de mensagens.
 */
export function abasVisiveisDoAluno(
  features: FlagsDoAluno | null | undefined,
  systemSettings: AjustesDoSistema | null | undefined
): DashboardTab[] {
  const f = features ?? {};
  const s = systemSettings ?? {};

  const abas: DashboardTab[] = ['general'];

  if (f.solicitacoesAcademicas === true) abas.push('documents');
  if (f.forum === true && f.mensagensDiretas === true && s.allowDirectMessages === true) {
    abas.push('messages');
  }
  if (f.materiaisComplementares === true) abas.push('library');
  if (f.eventosWebinars === true) abas.push('events');
  abas.push('faq');
  if (f.perfilBasico === true) abas.push('settings');

  return abas;
}

/** A aba está visível para este aluno? */
export function abaVisivelParaAluno(
  aba: DashboardTab,
  features: FlagsDoAluno | null | undefined,
  systemSettings: AjustesDoSistema | null | undefined
): boolean {
  return abasVisiveisDoAluno(features, systemSettings).includes(aba);
}
