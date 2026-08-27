/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { ArrowLeft } from 'lucide-react';

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
  onBack: () => void;
  children: React.ReactNode;
}

/**
 * Moldura comum das páginas dedicadas do portal público: botão de voltar,
 * cabeçalho institucional e área de conteúdo. Evita repetir esse bloco em cada
 * página nova.
 */
export const PageShell: React.FC<PageShellProps> = ({
  eyebrow,
  title,
  description,
  accent = '#540D6E',
  align = 'left',
  background = 'bg-white',
  onBack,
  children,
}) => {
  const isCentered = align === 'center';

  return (
    <div className={`${background} min-h-[70vh] py-10 px-4 animate-in fade-in duration-300`}>
      <div className="mx-auto max-w-7xl space-y-8">
        <button
          type="button"
          onClick={onBack}
          className="group flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900 transition-all bg-white border border-slate-200 px-4 py-2 rounded-xl cursor-pointer shadow-3xs"
          title="Voltar ao Portal Inicial"
        >
          <ArrowLeft className="h-4 w-4 group-hover:-translate-x-1 transition-transform" />
          <span>Voltar</span>
        </button>

        <div
          className={`space-y-2 border-b border-slate-200 pb-6 ${
            isCentered ? 'text-center max-w-3xl mx-auto' : 'text-left'
          }`}
        >
          <span
            className="text-[10px] font-extrabold uppercase tracking-widest block font-mono"
            style={{ color: accent }}
          >
            {eyebrow}
          </span>
          <h3 className="text-2xl md:text-3.5xl font-black text-slate-900 uppercase tracking-tight font-serif">
            {title}
          </h3>
          {description && (
            <p
              className={`text-xs md:text-sm text-slate-500 leading-relaxed font-light max-w-2xl ${
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
