/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { createPortal } from 'react-dom';

/**
 * Menu suspenso que abre ANCORADO num botão, mas renderizado fora da árvore
 * dele (portal no <body>).
 *
 * Por que portal e não `absolute`: o menu de ações da tabela de alunos vivia
 * dentro de `<div className="overflow-x-auto">`. Pela regra do CSS, quando um
 * eixo do overflow deixa de ser `visible`, o outro eixo `visible` passa a
 * computar `auto` — ou seja, aquele contêiner recorta TAMBÉM na vertical. Nas
 * últimas linhas da tabela o menu era cortado pela borda do contêiner (e ainda
 * criava uma barra de rolagem vertical parasita), então quem clicava nos três
 * pontos não conseguia ler as opções. Nenhum z-index resolve recorte por
 * overflow; sair da árvore resolve.
 *
 * A posição é recalculada em scroll e resize (captura, para pegar também a
 * rolagem do contêiner interno da tabela), e o menu vira para cima quando não
 * couber abaixo do botão.
 */
interface AnchoredMenuProps {
  /** Elemento que serve de âncora — normalmente o botão que abriu o menu. */
  anchor: HTMLElement | null;
  /** Fecha o menu: clique fora, Escape ou seleção de um item. */
  onClose: () => void;
  /** Largura do painel em px. */
  width?: number;
  children: React.ReactNode;
}

/** Respiro entre o menu e a borda da janela, para não colar. */
const MARGEM = 8;

export const AnchoredMenu: React.FC<AnchoredMenuProps> = ({
  anchor,
  onClose,
  width = 192,
  children,
}) => {
  const painel = React.useRef<HTMLDivElement | null>(null);
  const [pos, setPos] = React.useState<{ top: number; left: number } | null>(null);

  const reposicionar = React.useCallback(() => {
    if (!anchor) return;
    const botao = anchor.getBoundingClientRect();
    const altura = painel.current?.offsetHeight ?? 0;
    const alturaJanela = window.innerHeight;
    const larguraJanela = window.innerWidth;

    // Abaixo do botão por padrão; acima se não couber (últimas linhas da tabela).
    const abaixo = botao.bottom + 4;
    const cabeAbaixo = altura === 0 || abaixo + altura + MARGEM <= alturaJanela;
    const top = cabeAbaixo ? abaixo : Math.max(MARGEM, botao.top - 4 - altura);

    // Alinhado à direita do botão, sem vazar para fora da janela.
    const direita = botao.right - width;
    const left = Math.min(Math.max(MARGEM, direita), Math.max(MARGEM, larguraJanela - width - MARGEM));

    setPos({ top, left });
  }, [anchor, width]);

  // Primeiro cálculo antes da pintura: evita o menu "pular" de lugar.
  React.useLayoutEffect(() => {
    reposicionar();
  }, [reposicionar]);

  // Segundo cálculo depois de existir altura medida, para decidir cima/baixo.
  React.useLayoutEffect(() => {
    if (pos !== null && painel.current?.offsetHeight) reposicionar();
    // Só quando a âncora muda: reagir a `pos` aqui geraria laço infinito.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [anchor]);

  React.useEffect(() => {
    const aoRolar = () => reposicionar();
    const aoTeclar = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    // `capture` para acompanhar a rolagem do contêiner da tabela, que não
    // borbulha para window.
    window.addEventListener('scroll', aoRolar, true);
    window.addEventListener('resize', aoRolar);
    window.addEventListener('keydown', aoTeclar);
    return () => {
      window.removeEventListener('scroll', aoRolar, true);
      window.removeEventListener('resize', aoRolar);
      window.removeEventListener('keydown', aoTeclar);
    };
  }, [reposicionar, onClose]);

  if (!anchor) return null;

  return createPortal(
    <>
      {/* Camada de clique-fora: cobre a tela toda e fica atrás do painel. */}
      <div className="fixed inset-0 z-[60]" onClick={onClose} />
      <div
        ref={painel}
        // Sem role="menu" de propósito: os itens são <button> comuns, e um
        // role="menu" sem role="menuitem" nos filhos faz o leitor de tela
        // anunciar um menu vazio. Melhor não prometer o padrão do que prometê-lo
        // pela metade.
        style={{ top: pos?.top ?? 0, left: pos?.left ?? 0, width, visibility: pos ? 'visible' : 'hidden' }}
        className="fixed z-[61] bg-white border border-slate-200 rounded-[10px] shadow-xl py-1.5 text-left animate-in fade-in duration-150"
      >
        {children}
      </div>
    </>,
    document.body
  );
};
