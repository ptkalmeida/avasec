/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';

/**
 * Trilha de navegação — o substituto dos botões "Voltar".
 *
 * O produto tinha **cinco desenhos diferentes do mesmo botão**: `BackButton.tsx`,
 * o botão embutido no `PageShell`, uma cópia colada à mão no `App.tsx`, o "Página
 * Inicial" do cabeçalho logado e um botão de 9 px dentro do cartão de saudação do
 * aluno. E o do `PageShell` mandava sempre `goToPage('landing')`: dizia "Voltar"
 * e ia para a Início, não para onde a pessoa veio.
 *
 * No painel do administrador, o mesmo botão aparecia oito vezes com o texto
 * "Voltar ao Painel Administrativo", duplicando o primeiro item de uma barra
 * lateral que está sempre visível.
 *
 * A trilha resolve os dois problemas de uma vez: diz ONDE a pessoa está — que é
 * o que "Voltar" nunca disse — e oferece cada nível acima como destino. Fica
 * sempre no mesmo lugar, logo abaixo do cabeçalho, porque um controle de
 * navegação que muda de posição obriga a procurá-lo a cada tela.
 *
 * "Início" é inserido AQUI, sempre em primeiro, e nunca pelos chamadores: era
 * assim que os textos divergiam entre telas.
 */

export interface BreadcrumbItem {
  rotulo: string;
  /** Ausente = degrau atual, não clicável. */
  onClick?: () => void;
}

interface BreadcrumbProps {
  /** Degraus DEPOIS do Início. Lista vazia não desenha a barra. */
  items: BreadcrumbItem[];
  /** Vai para a página inicial do portal. */
  onHome: () => void;
  /** Rótulo do primeiro degrau. Os painéis internos usam o próprio. */
  rotuloInicio?: string;
}

export const Breadcrumb: React.FC<BreadcrumbProps> = ({ items, onHome, rotuloInicio = 'Início' }) => {
  /*
   * Sem degraus, sem barra. Uma trilha com "Início" sozinho não informa nada e
   * ainda empurra o conteúdo 48 px para baixo — foi por isso que a página
   * inicial ficou de fora.
   */
  if (items.length === 0) return null;

  const degraus: BreadcrumbItem[] = [{ rotulo: rotuloInicio, onClick: onHome }, ...items];

  return (
    <nav
      aria-label="Trilha de navegação"
      className="w-full bg-[#f4f2ef] border-t border-[#e4e1dc]"
    >
      <ol className="mx-auto max-w-[1280px] px-8 h-12 flex items-center gap-2.5 overflow-x-auto no-scrollbar">
        {degraus.map((degrau, i) => {
          const ehAtual = i === degraus.length - 1;

          return (
            <li key={`${degrau.rotulo}-${i}`} className="flex items-center gap-2.5 shrink-0">
              {i > 0 && (
                // Separador decorativo: o leitor de tela já anuncia a lista.
                <span className="text-[#9aa1ae] select-none" aria-hidden="true">›</span>
              )}

              {ehAtual || degrau.onClick === undefined ? (
                <span
                  className="text-rotulo font-semibold text-[#1d2432] whitespace-nowrap"
                  aria-current={ehAtual ? 'page' : undefined}
                >
                  {degrau.rotulo}
                </span>
              ) : (
                <button
                  onClick={degrau.onClick}
                  className="text-rotulo font-medium text-[#540D6E] underline underline-offset-[3px] hover:text-[#42095a] transition-colors cursor-pointer whitespace-nowrap"
                >
                  {degrau.rotulo}
                </button>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
};
