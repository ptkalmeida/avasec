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
  /** Cor institucional usada no sobretítulo e na barra de destaque. */
  accent?: string;
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
  accent = '#540D6E',
  align = 'left',
  background = 'bg-white',
  children,
}) => {
  const isCentered = align === 'center';

  return (
    <div className={`${background} min-h-[70vh] py-10 px-4 animate-in fade-in duration-300`}>
      <div className="mx-auto max-w-7xl space-y-8">
        <div
          className={`space-y-2 border-b border-slate-200 pb-6 ${
            isCentered ? 'text-center max-w-3xl mx-auto' : 'text-left'
          }`}
        >
          <span
            className="text-sobretitulo uppercase block"
            style={{ color: accent }}
          >
            {eyebrow}
          </span>
          {/*
            <h1>, e nao <h3>: este e o titulo DA pagina. As nove paginas
            institucionais nao tinham nenhum <h1> — para quem navega por
            cabecalhos, o titulo da pagina era anunciado como subsubtitulo.

            `md:text-3.5xl` estava aqui e nao existe no tema (Tailwind 4 nao
            gera classe para tamanho nao declarado): o titulo ficava em 24px em
            qualquer largura de tela, e ninguem via erro nenhum.
          */}
          <h1 className="text-secao md:text-pagina font-semibold text-escult-ink tracking-tight font-serif">
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
          <div
            className={`h-1.5 w-16 rounded-full ${isCentered ? 'mx-auto' : ''}`}
            style={{ backgroundColor: accent }}
          />
        </div>

        {children}
      </div>
    </div>
  );
};
