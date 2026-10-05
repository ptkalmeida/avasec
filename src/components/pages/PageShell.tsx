/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';

interface PageShellProps {
  /** Sobretítulo curto em caixa alta (ex.: "Manual do Estudante"). */
  eyebrow: string;
  title: string;
  description?: string;
  align?: 'left' | 'center';
  /** Classe utilitária de fundo da página inteira. */
  background?: string;
  children: React.ReactNode;
}

/**
 * Moldura comum das páginas dedicadas do portal público: cabeçalho
 * institucional e área de conteúdo.
 *
 * O botão "Voltar" SAIU daqui (Bloco 1 do handoff). Ele mandava sempre
 * `goToPage('landing')` — dizia "Voltar" e ia para a Início, não para onde a
 * pessoa veio — e era um de cinco desenhos diferentes do mesmo botão espalhados
 * pelo produto. Quem diz onde a pessoa está agora é o `Breadcrumb`, renderizado
 * uma única vez logo abaixo do cabeçalho, em largura total e sempre no mesmo
 * lugar. Aqui dentro ele ficaria dentro do respiro da página, e a posição
 * mudaria de tela para tela.
 */
export const PageShell: React.FC<PageShellProps> = ({
  eyebrow,
  title,
  description,
  align = 'left',
  background = 'bg-white',
  children,
}) => {
  const isCentered = align === 'center';

  /*
   * Topo das telas 05 e 06 da skill ava-frontend-redesign (planejamento 13):
   * faixa clara, sobretitulo com filete dourado, titulo marinho e a
   * composicao geometrica a direita (so em tela larga, onde ha espaco sem
   * cobrir o texto). A barra colorida embaixo do titulo e o `accent` sairam:
   * a cor do sobretitulo passou a ser uma so no portal.
   */
  return (
    <div className={`${background} min-h-[70vh] animate-in fade-in duration-300`}>
      <section className="relative overflow-hidden border-b border-ava-borda bg-gradient-to-b from-white to-ava-faixa/60 px-4 py-10 md:py-14">
        <div aria-hidden="true" className="hidden xl:block absolute right-24 top-8 h-40 w-20 rounded-r-full bg-ava-marinho" />
        <div aria-hidden="true" className="hidden xl:block absolute right-[11.5rem] top-16 h-20 w-10 rounded-l-full bg-ava-dourado" />
        <div aria-hidden="true" className="hidden xl:block absolute right-8 top-6 h-16 w-16 bg-ava-rosa" />
        <div aria-hidden="true" className="hidden xl:block absolute right-10 bottom-8 h-20 w-20 bg-ava-acao" />

        <div
          className={`relative mx-auto max-w-7xl space-y-3 ${
            isCentered ? 'text-center' : 'text-left'
          }`}
        >
          <p className="filete text-sobretitulo uppercase text-ava-tinta">
            {eyebrow}
          </p>
          {/*
            <h1>, e nao <h3>: este e o titulo DA pagina. As nove paginas
            institucionais nao tinham nenhum <h1> — para quem navega por
            cabecalhos, o titulo da pagina era anunciado como subsubtitulo.

            `md:text-3.5xl` estava aqui e nao existe no tema (Tailwind 4 nao
            gera classe para tamanho nao declarado): o titulo ficava em 24px em
            qualquer largura de tela, e ninguem via erro nenhum.
          */}
          <h1 className={`text-secao md:text-pagina text-ava-tinta tracking-tight font-titulo break-words ${isCentered ? 'max-w-3xl mx-auto' : 'max-w-3xl'}`}>
            {title}
          </h1>
          {description && (
            <p
              className={`text-corpo text-escult-ink-2 max-w-2xl ${
                isCentered ? 'mx-auto' : ''
              }`}
            >
              {description}
            </p>
          )}
        </div>
      </section>

      <div className="mx-auto max-w-7xl px-4 py-10 space-y-8">
        {children}
      </div>
    </div>
  );
};
