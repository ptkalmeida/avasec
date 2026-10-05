/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems } from '../../utils/sitePageContent';

interface ProjetoPageProps {
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
    // A mencao nominal ao educador homenageado saiu de todo o site
    // (10/09/2026). O que fica e a concepcao pedagogica, que e o que a frase
    // de fato descreve.
    description: 'Nossa prática integra teoria crítica e o fazer artístico imediato: a educação parte do repertório de quem aprende e se volta para a leitura crítica da realidade.'
  }
];

export const ProjetoPage: React.FC<ProjetoPageProps> = ({ content }) => {
  const items = pageItems(content, DEFAULT_ITEMS);
  const imageUrl = pageField(content, 'imageUrl', DEFAULT_IMAGE);

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Iniciativa de fomento público')}
      title={pageField(content, 'title', 'O Projeto Pedagógico')}
    >
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        {/* Imagem decorativa */}
        <div className="lg:col-span-5 relative flex items-center justify-center">
          {/* O contorno deslocado saiu: com `w-full` e `left-4` ele passava 16px da
              tela em 320px. No lugar, o par geometrico da skill, dentro da caixa. */}
          <div className="relative w-full max-w-[22rem] aspect-square shrink-0">
            <div aria-hidden="true" className="absolute -top-0 -left-0 h-16 w-16 rounded-br-full bg-ava-dourado z-10" />
            <div aria-hidden="true" className="absolute bottom-0 right-0 h-16 w-16 bg-ava-rosa z-10" />
            <div className="w-full h-full rounded-2xl overflow-hidden shadow-xl relative bg-ava-marinho">
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
          <p className="text-corpo text-escult-ink-2 leading-relaxed">
            {pageField(content, 'description', 'A Escola Estadual da Cultura é um projeto estratégico estatal gerido pela Diretoria de Formação e Qualificação de Trabalhadores da Cultura. Nosso plano político-pedagógico tem como compromisso democratizar as ferramentas da Economia Criativa.')}
          </p>

          <div className="space-y-4 pt-2">
            {items.map((p, index) => (
              <div key={p.id} className="flex gap-4 rounded-2xl border border-ava-borda bg-white p-4.5">
                <div className="h-11 w-11 rounded-full bg-ava-icone-fundo flex items-center justify-center text-ava-acao text-lg font-black shrink-0">
                  {index + 1}
                </div>
                <div className="space-y-1 min-w-0">
                  <strong className="text-rotulo text-ava-tinta font-bold block">{p.title}</strong>
                  <p className="text-apoio text-escult-ink-2 leading-relaxed">{p.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </PageShell>
  );
};
