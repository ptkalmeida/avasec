/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { SitePageContent, SitePageItem } from '../types';

/**
 * Lê um campo de texto do conteúdo editável, caindo no padrão embutido quando
 * a API não respondeu ainda (ou está offline) ou quando o admin deixou o campo
 * em branco. Assim a página pública nunca aparece vazia.
 */
export const pageField = (
  content: SitePageContent | undefined,
  key: string,
  fallback: string
): string => {
  const value = content?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value : fallback;
};

/**
 * Lista de itens da página, caindo no padrão embutido quando não há conteúdo
 * salvo. Lista salva vazia também usa o padrão — página pública em branco é
 * quase sempre erro de edição, não intenção.
 */
export const pageItems = (
  content: SitePageContent | undefined,
  fallback: SitePageItem[]
): SitePageItem[] =>
  content && Array.isArray(content.items) && content.items.length > 0
    ? content.items
    : fallback;

/**
 * Filtra itens por um termo de busca, olhando só os campos informados.
 * Termo vazio devolve lista vazia — a chamada serve para decidir se há
 * resultados a exibir, não para listar tudo.
 */
export const filterSiteItems = (
  items: SitePageItem[],
  query: string,
  fields: string[]
): SitePageItem[] => {
  const term = query.trim().toLowerCase();
  if (term === '') return [];

  return items.filter((item) =>
    fields.some((field) => (item[field] ?? '').toLowerCase().includes(term))
  );
};
