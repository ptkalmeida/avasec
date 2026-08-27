/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems } from '../../utils/sitePageContent';

interface ProjetoPageProps {
  onBack: () => void;
  /** Conteúdo editado pelo admin; ausente = usa os padrões abaixo. */
  content?: SitePageContent;
}

const DEFAULT_IMAGE = 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&q=80&w=600';

/** Padrão de fábrica, usado quando a API não respondeu (espelha o backend). */
const DEFAULT_ITEMS: SitePageItem[] = [
  {
    id: 'pilar-1',
    title: 'Foco na Descentralização e Acesso Público',
    description: 'Trilhamos caminhos para alcançar comunidades distantes dos grandes eixos culturais, proporcionando qualificação técnica para jovens e adultos.'
  },
  {
    id: 'pilar-2',
    title: 'Fomento à Lei Paulo Gustavo e Editais Públicos',
    description: 'Nossos conteúdos auxiliam o fazedor de cultura a elaborar propostas robustas, captar recursos em editais governamentais e prestar contas de forma simplificada.'
  },
  {
    id: 'pilar-3',
    title: 'Pedagogia Decolonial e Inclusiva',
    description: 'Celebramos e nos inspiramos no grande educador patrono Paulo Freire e na vanguarda negra de Solano Trindade, integrando teoria crítica com prática imediata do fazer artístico.'
  }
];

export const ProjetoPage: React.FC<ProjetoPageProps> = ({ onBack, content }) => {
  const items = pageItems(content, DEFAULT_ITEMS);
  const imageUrl = pageField(content, 'imageUrl', DEFAULT_IMAGE);

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Iniciativa de fomento público')}
      title={pageField(content, 'title', 'O Projeto Pedagógico')}
      background="bg-slate-50"
      onBack={onBack}
    >
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        {/* Imagem decorativa */}
        <div className="lg:col-span-5 relative flex items-center justify-center">
          <div className="relative w-80 h-80 sm:w-90 sm:h-90 shrink-0 flex items-center justify-center">
            <div className="absolute top-4 left-4 w-full h-full border-4 border-[#FFD23F]/30 rounded-3xl pointer-events-none" />
            <div className="w-full h-full rounded-3xl overflow-hidden border-4 border-white shadow-xl relative bg-slate-900">
              <img
                src={imageUrl}
                alt="Escola Estadual da Cultura"
                className="w-full h-full object-cover filter brightness-95"
                referrerPolicy="no-referrer"
              />
            </div>
          </div>
        </div>

        {/* Texto e pilares do plano político-pedagógico */}
        <div className="lg:col-span-7 space-y-6 text-left">
          <p className="text-xs md:text-sm text-slate-500 leading-relaxed font-light">
            {pageField(content, 'description', 'A Escola Estadual da Cultura é um projeto estratégico estatal gerido pela Diretoria de Formação e Qualificação de Trabalhadores da Cultura. Nosso plano político-pedagógico tem como compromisso democratizar as ferramentas da Economia Criativa.')}
          </p>

          <div className="space-y-4 pt-2">
            {items.map((p, index) => (
              <div key={p.id} className="flex gap-3.5">
                <div className="h-6.5 w-6.5 rounded-full bg-[#540D6E]/10 flex items-center justify-center text-[#540D6E] text-xs font-black shrink-0 font-mono mt-0.5">
                  {index + 1}
                </div>
                <div className="space-y-0.5">
                  <strong className="text-xs text-slate-800 font-bold block">{p.title}</strong>
                  <p className="text-xs text-slate-400 font-light leading-relaxed">{p.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </PageShell>
  );
};
