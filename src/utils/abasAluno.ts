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
  certificados?: boolean;
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

/*
 * ---------------------------------------------------------------------------
 * Bloco 6 do handoff: separar LUGAR de AÇÃO.
 *
 * A barra tinha sete botões idênticos misturando as duas coisas. "Central de
 * Ajuda / FAQ" está desenhada como aba e **abre uma gaveta lateral** — não é
 * um lugar, é uma ação; e "Meu Perfil" é área pessoal, não seção de estudo.
 * Enquanto isso, os dois lugares que o aluno mais procura não estavam na
 * barra: o curso em que ele está matriculado e os certificados.
 *
 * Onde eu me afastei do handoff, e por quê: ele lista a navegação como
 * "Painel · Meu curso · Certificados", o que deixaria Biblioteca Digital fora
 * da navegação — e ela está LIGADA hoje (`materiaisComplementares: true`).
 * Tirar a entrada sem mover a tela seria remover acesso a um recurso no ar.
 * Então a navegação leva os lugares que existem, e as flags continuam
 * decidindo quais aparecem.
 * ---------------------------------------------------------------------------
 */

/** Um lugar da navegação do aluno. */
export interface LugarDoAluno {
  id: 'painel' | 'curso' | 'certificados' | 'documentos' | 'biblioteca' | 'mensagens' | 'eventos';
  rotulo: string;
  /**
   * Aba do painel que o lugar abre.
   *
   * Ausente em `curso` (que abre a tela do curso, fora da lista de abas) e em
   * `certificados` (que hoje vive numa sub-aba do Perfil — ver o comentário em
   * `StudentDashboard`).
   */
  aba?: DashboardTab;
}

/** Uma ação do canto superior — não é lugar, e por isso não fica na navegação. */
export interface AcaoDoAluno {
  id: 'ajuda' | 'perfil';
  rotulo: string;
}

/** O que o painel precisa dizer sobre a matrícula para montar a navegação. */
export interface SituacaoDoAluno {
  /** Há curso ativo? Sem curso, "Meu curso" não leva a lugar nenhum. */
  temCursoAtivo?: boolean;
}

/**
 * Os lugares, na ordem da navegação.
 *
 * "Painel" é sempre o primeiro e nunca depende de flag: é a entrada.
 */
export function lugaresDoAluno(
  features: FlagsDoAluno | null | undefined,
  systemSettings: AjustesDoSistema | null | undefined,
  situacao: SituacaoDoAluno | null | undefined = {}
): LugarDoAluno[] {
  const f = features ?? {};
  const s = situacao ?? {};
  const visiveis = abasVisiveisDoAluno(features, systemSettings);

  const lugares: LugarDoAluno[] = [{ id: 'painel', rotulo: 'Painel', aba: 'general' }];

  if (s.temCursoAtivo === true) {
    lugares.push({ id: 'curso', rotulo: 'Meu curso' });
  }

  // Certificado é documento acadêmico: o lugar existe mesmo antes de haver um
  // emitido, porque é onde se acompanha o que falta para emitir.
  if (f.certificados !== false) {
    lugares.push({ id: 'certificados', rotulo: 'Certificados' });
  }

  if (visiveis.includes('documents')) lugares.push({ id: 'documentos', rotulo: 'Documentos', aba: 'documents' });
  if (visiveis.includes('library')) lugares.push({ id: 'biblioteca', rotulo: 'Biblioteca', aba: 'library' });
  if (visiveis.includes('messages')) lugares.push({ id: 'mensagens', rotulo: 'Mensagens', aba: 'messages' });
  if (visiveis.includes('events')) lugares.push({ id: 'eventos', rotulo: 'Eventos', aba: 'events' });

  return lugares;
}

/**
 * As ações do canto superior direito.
 *
 * Ajuda não depende de flag — é a mesma decisão de sempre, agora explícita:
 * quem não encontra o caminho precisa de ajuda justamente quando o resto está
 * desligado.
 */
export function acoesDoAluno(
  features: FlagsDoAluno | null | undefined,
  systemSettings: AjustesDoSistema | null | undefined
): AcaoDoAluno[] {
  const acoes: AcaoDoAluno[] = [{ id: 'ajuda', rotulo: 'Ajuda' }];

  if (abasVisiveisDoAluno(features, systemSettings).includes('settings')) {
    acoes.push({ id: 'perfil', rotulo: 'Perfil' });
  }

  return acoes;
}
