/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems, filterSiteItems } from '../../utils/sitePageContent';

/** Campos varridos pela busca do topo. */
export const NEWS_SEARCH_FIELDS = ['title', 'description'];

/** Padrão de fábrica, usado quando a API não respondeu (espelha o backend). */
export const DEFAULT_NEWS_ITEMS: SitePageItem[] = [
  {
    id: 'noticia-1',
    date: '20 mai. 2026',
    tag: 'Video Mapping',
    title: 'AVASEC abre inscrições para curso de Video Mapping focado no cenário cultural',
    image: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&auto=format&fit=crop&q=70',
    description: 'Uma imersão completa na arte digital e projeção mapeada para fachadas históricas, palcos e intervenções urbanas de impacto.'
  },
  {
    id: 'noticia-2',
    date: '16 mai. 2026',
    tag: 'Prestação de Contas',
    title: 'Nova turma do Curso de Prestação de Contas de Propostas Simplificadas',
    image: 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600&auto=format&fit=crop&q=70',
    description: 'Domine sem mistérios as exigências contábeis e fiscais das leis de fomento, garantindo a aprovação tranquila do seu relatório.'
  },
  {
    id: 'noticia-3',
    date: '14 mai. 2026',
    tag: 'Propostas Simplificadas',
    title: 'Abertas as inscrições para a nova turma do curso de Submissão de Propostas',
    image: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=600&auto=format&fit=crop&q=70',
    description: 'Como transformar as suas ideias artísticas em formatos de propostas campeãs para concorrer a editais públicos e privados.'
  }
];

interface NoticiasPageProps {
  onBack: () => void;
  /** Termo vindo da busca global do topo; vazio lista todas as notícias. */
  searchQuery: string;
  onClearSearch: () => void;
  /** Abre o modal de login (a leitura completa exige autenticação). */
  onRequireLogin: () => void;
  /** Conteúdo editado pelo admin; ausente = usa os padrões acima. */
  content?: SitePageContent;
}

export const NoticiasPage: React.FC<NoticiasPageProps> = ({
  onBack,
  searchQuery,
  onClearSearch,
  onRequireLogin,
  content,
}) => {
  const [activeIndex, setActiveIndex] = useState(0);

  const allNews = pageItems(content, DEFAULT_NEWS_ITEMS);
  const displayedNews = searchQuery.trim() === ''
    ? allNews
    : filterSiteItems(allNews, searchQuery, NEWS_SEARCH_FIELDS);

  const goPrev = () => setActiveIndex((prev) => (prev - 1 + allNews.length) % allNews.length);
  const goNext = () => setActiveIndex((prev) => (prev + 1) % allNews.length);

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Destaques Letivos')}
      title={pageField(content, 'title', 'Notícias & Novidades')}
      description={pageField(content, 'description', 'Acompanhe os informativos, aberturas de turma e novidades do Portal AVASEC.')}
      accent="#EE4266"
      background="bg-slate-50"
      onBack={onBack}
    >
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <span className="text-[10px] text-slate-400 font-mono font-bold">Portal de Notícias AVASEC</span>
        <div className="flex gap-1.5">
          <button
            onClick={goPrev}
            className="h-9 w-9 rounded-full bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-700 transition-colors shadow-3xs cursor-pointer"
            title="Anterior"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <button
            onClick={goNext}
            className="h-9 w-9 rounded-full bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-700 transition-colors shadow-3xs cursor-pointer"
            title="Próximo"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
        {displayedNews.length === 0 ? (
          <div className="col-span-full rounded-2xl border border-dashed border-slate-300 p-12 text-center bg-white shadow-3xs">
            <h4 className="text-[#EE4266] font-black text-sm uppercase tracking-wider mb-2 font-mono">Sem Resultados</h4>
            <p className="text-slate-500 text-xs leading-relaxed max-w-md mx-auto">
              Nenhuma notícia coincide com a sua busca por "
              <strong className="text-slate-800 font-bold">{searchQuery}</strong>".
            </p>
            <button
              onClick={onClearSearch}
              className="mt-4 px-4 py-2 bg-[#EE4266] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition-colors uppercase tracking-wider cursor-pointer font-sans"
            >
              Limpar Filtro de Busca
            </button>
          </div>
        ) : (
          displayedNews.map((news, idx) => {
            const isFocus = idx === (activeIndex % displayedNews.length);

            return (
              <div
                key={news.id}
                className={`bg-white rounded-2xl border transition-all duration-300 overflow-hidden shadow-2xs hover:shadow-md flex flex-col justify-between ${
                  isFocus ? 'border-[#EE4266] ring-1 ring-[#EE4266]' : 'border-slate-200'
                }`}
              >
                {news.image && (
                  <div className="h-44 overflow-hidden relative">
                    <img
                      src={news.image}
                      alt={news.title}
                      className="w-full h-full object-cover filter contrast-110 sepia-[5%] hover:scale-105 transition-transform duration-500"
                      referrerPolicy="no-referrer"
                    />
                    {news.tag && (
                      <div className="absolute top-3 left-3 bg-[#EE4266] text-white text-[9px] font-mono uppercase tracking-wider px-2 py-0.5 rounded font-bold">
                        {news.tag}
                      </div>
                    )}
                  </div>
                )}

                <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                  <div className="space-y-2">
                    <span className="text-[10px] text-slate-400 font-mono font-bold block">{news.date}</span>
                    <h4 className="text-xs font-bold text-slate-900 leading-snug line-clamp-2 h-9 font-serif">
                      {news.title}
                    </h4>
                    <p className="text-[10.5px] text-slate-500 leading-relaxed font-light line-clamp-3">
                      {news.description}
                    </p>
                  </div>

                  <button
                    onClick={onRequireLogin}
                    className="w-full text-center py-2.5 rounded-xl bg-slate-50 hover:bg-slate-900 hover:text-white transition-colors text-slate-650 border border-slate-150 text-[10px] font-black uppercase tracking-wider cursor-pointer"
                  >
                    Ler Mais Informações
                  </button>
                </div>
              </div>
            );
          })
        )}
      </div>
    </PageShell>
  );
};
