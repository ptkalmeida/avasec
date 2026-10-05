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
  /** Termo vindo da busca global do topo; vazio lista todas as notícias. */
  searchQuery: string;
  onClearSearch: () => void;
  /** Abre o modal de login (a leitura completa exige autenticação). */
  onRequireLogin: () => void;
  /** Conteúdo editado pelo admin; ausente = usa os padrões acima. */
  content?: SitePageContent;
}

export const NoticiasPage: React.FC<NoticiasPageProps> = ({
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

  /*
   * O sobretitulo usa o `accent` padrao do PageShell (o violeta do tema).
   * Antes era o vermelho #d62f52; no redesenho (planejamento 12) o vermelho
   * saiu de titulo e sobretitulo e ficou para erro e alerta.
   */
  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Destaques Letivos')}
      title={pageField(content, 'title', 'Notícias & Novidades')}
      description={pageField(content, 'description', 'Acompanhe os informativos, aberturas de turma e novidades do Portal AVASEC.')}
    >
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <span className="filete text-sobretitulo uppercase text-ava-tinta">Portal de Notícias AVASEC</span>
        <div className="flex gap-1.5">
          <button
            onClick={goPrev}
            className="h-10 w-10 rounded-full bg-white border border-ava-borda hover:bg-ava-faixa flex items-center justify-center text-ava-acao transition-colors shadow-3xs cursor-pointer"
            title="Anterior"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <button
            onClick={goNext}
            className="h-10 w-10 rounded-full bg-white border border-ava-borda hover:bg-ava-faixa flex items-center justify-center text-ava-acao transition-colors shadow-3xs cursor-pointer"
            title="Próximo"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
        {displayedNews.length === 0 ? (
          <div className="col-span-full rounded-2xl border border-dashed border-ava-borda p-12 text-center bg-ava-faixa/50">
            <h4 className="text-ava-tinta font-titulo text-cartao mb-2">Sem Resultados</h4>
            <p className="text-escult-ink-2 text-apoio leading-relaxed max-w-md mx-auto">
              Nenhuma notícia coincide com a sua busca por "
              <strong className="text-ava-tinta font-bold">{searchQuery}</strong>".
            </p>
            <button
              onClick={onClearSearch}
              className="mt-4 px-5 py-2.5 bg-ava-acao text-white text-rotulo font-semibold rounded-[10px] hover:bg-ava-acao-escuro transition-colors cursor-pointer font-sans"
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
                  isFocus ? 'border-ava-acao ring-1 ring-ava-acao' : 'border-ava-borda'
                }`}
              >
                {news.image && (
                  <div className="h-44 overflow-hidden relative">
                    <img
                      src={news.image}
                      alt={news.title}
                      className="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                      referrerPolicy="no-referrer"
                    />
                    {news.tag && (
                      <div className="absolute top-3 left-3 bg-white text-ava-acao text-nota font-semibold uppercase tracking-wide px-2.5 py-1 rounded-full shadow-sm max-w-[calc(100%-1.5rem)] truncate">
                        {news.tag}
                      </div>
                    )}
                  </div>
                )}

                <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                  <div className="space-y-2">
                    <span className="text-apoio text-escult-ink-2 font-bold block">{news.date}</span>
                    <h4 className="text-cartao text-ava-tinta leading-snug line-clamp-2 font-titulo">
                      {news.title}
                    </h4>
                    <p className="text-apoio text-escult-ink-2 leading-relaxed line-clamp-3">
                      {news.description}
                    </p>
                  </div>

                  <button
                    onClick={onRequireLogin}
                    className="w-full text-center py-2.5 rounded-[10px] bg-white hover:bg-ava-acao hover:text-white transition-colors text-ava-acao border border-ava-acao text-rotulo font-semibold cursor-pointer"
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
