/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Estrutura do menu do portal público.
 *
 * Eram **nove** entradas de 11 px, caixa alta, todas com o mesmo peso e a mesma
 * cor, e **nenhuma indicação da página atual** — quem estava em "Orientações"
 * não tinha como saber disso pelo menu. Nove itens indistinguíveis não são um
 * menu: são uma lista que a pessoa lê inteira, toda vez.
 *
 * Passa a ter quatro: dois links diretos e dois grupos. "Início" sai porque o
 * logotipo já faz isso e já anuncia `title="Voltar ao Portal Inicial"` — duas
 * portas para a mesma tela gastam uma vaga do menu sem acrescentar destino.
 *
 * Por que a estrutura vive aqui e não no JSX: a pergunta "qual entrada está
 * ativa?" tem resposta diferente para link e para grupo — em "Notícias", quem
 * acende é **A Escola**, que não é a página. Essa regra erra em silêncio (o
 * ponto simplesmente não aparece) e por isso é testada fora do navegador.
 */

import { PortalView } from '../router/portalRoutes';

/** Um destino dentro de um grupo do menu. */
export interface ItemDeGrupo {
  rotulo: string;
  /** Uma linha dizendo o que a pessoa encontra lá. */
  descricao: string;
  view: PortalView;
}

export type EntradaMenu =
  | { tipo: 'link'; rotulo: string; view: PortalView }
  | { tipo: 'grupo'; rotulo: string; id: 'escola' | 'ajuda'; itens: ItemDeGrupo[] };

/**
 * As quatro entradas, na ordem em que aparecem.
 *
 * Cursos e Certificados vêm primeiro por serem as duas tarefas que trazem
 * alguém ao portal; o institucional e a ajuda ficam agrupados atrás.
 */
export const MENU_PUBLICO: readonly EntradaMenu[] = [
  { tipo: 'link', rotulo: 'Cursos', view: 'cursos' },
  { tipo: 'link', rotulo: 'Certificados', view: 'certificados' },
  {
    tipo: 'grupo',
    rotulo: 'A Escola',
    id: 'escola',
    itens: [
      { rotulo: 'O que é o AVA', descricao: 'Como funciona o ambiente virtual', view: 'o-ava' },
      { rotulo: 'O Projeto', descricao: 'A concepção pedagógica da escola', view: 'o-projeto' },
      { rotulo: 'Notícias', descricao: 'Avisos e novidades da rede', view: 'noticias' },
    ],
  },
  {
    tipo: 'grupo',
    rotulo: 'Ajuda',
    id: 'ajuda',
    itens: [
      { rotulo: 'Dúvidas frequentes', descricao: 'Respostas às perguntas mais comuns', view: 'duvidas' },
      { rotulo: 'Orientações ao estudante', descricao: 'Regras de frequência, prazos e certificados', view: 'orientacoes' },
      { rotulo: 'Calendário', descricao: 'Datas de matrícula e das turmas', view: 'calendario' },
    ],
  },
];

/**
 * Rótulo da entrada que deve aparecer como ativa, ou null.
 *
 * Numa página de dentro de um grupo, quem acende é o **grupo** — em "Notícias",
 * acende "A Escola". Devolve null na página inicial e em telas que não são do
 * portal público (`perfil`, `active_app`): acender uma entrada arbitrária seria
 * pior que não acender nenhuma, porque afirmaria uma localização falsa.
 */
export function entradaAtiva(view: PortalView): string | null {
  for (const entrada of MENU_PUBLICO) {
    if (entrada.tipo === 'link') {
      if (entrada.view === view) return entrada.rotulo;
      continue;
    }
    if (entrada.itens.some((i) => i.view === view)) return entrada.rotulo;
  }

  return null;
}

/** Todos os destinos alcançáveis pelo menu, para conferência. */
export function destinosDoMenu(): PortalView[] {
  return MENU_PUBLICO.flatMap((e) => (e.tipo === 'link' ? [e.view] : e.itens.map((i) => i.view)));
}
