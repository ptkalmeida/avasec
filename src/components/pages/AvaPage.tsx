/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { Video, Users, Award } from 'lucide-react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems } from '../../utils/sitePageContent';

interface AvaPageProps {
  /** Conteúdo editado pelo admin; ausente = usa os padrões abaixo. */
  content?: SitePageContent;
}

/** Padrão de fábrica, usado quando a API não respondeu (espelha o backend). */
const DEFAULT_ITEMS: SitePageItem[] = [
  {
    id: 'ava-1',
    title: 'Aulas Assíncronas',
    description: 'Assista às videoaulas gravadas quando e onde quiser, no seu próprio ritmo. Nosso player interativo permite que você retome os estudos exatamente de onde parou.'
  },
  {
    id: 'ava-2',
    title: 'Interação Próxima',
    description: 'Participe de mentorias coletivas ao vivo através do nosso Calendário e envie mensagens diretas aos professores e tutores especializados de cada trilha.'
  },
  {
    id: 'ava-3',
    title: 'Diplomas Válidos',
    description: 'Ao atingir os objetivos acadêmicos, emita um certificado oficial digital homologado pela Secretaria da Cultura do Estado com verificação em blockchain.'
  }
];

/**
 * Ícone é decoração cíclica — não é campo editável. A cor saiu daqui: nos
 * cartões da tela 05 (planejamento 13) o ícone é sempre azul num círculo claro.
 */
const ICONES = [Video, Users, Award];

export const AvaPage: React.FC<AvaPageProps> = ({ content }) => {
  const items = pageItems(content, DEFAULT_ITEMS);

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Ecossistema de Qualificação Digital')}
      title={pageField(content, 'title', 'O que é o AVA?')}
      description={pageField(content, 'description', 'O AVA (Ambiente Virtual de Aprendizagem) da Escola Estadual da Cultura é um ecossistema digital inteligente voltado para a formação continuada, democrático e acessível a todos os fazedores de cultura.')}
      align="center"
    >
      <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
        {items.map((item, index) => {
          const Icon = ICONES[index % ICONES.length];
          return (
            <div
              key={item.id}
              className="bg-white rounded-2xl p-6.5 border border-ava-borda shadow-3xs hover:shadow-md transition-all space-y-4 text-left"
            >
              <div className="h-14 w-14 rounded-full bg-ava-icone-fundo text-ava-acao flex items-center justify-center">
                <Icon className="h-6 w-6" aria-hidden="true" />
              </div>
              <h4 className="text-cartao text-ava-tinta font-titulo">{item.title}</h4>
              <p className="text-apoio text-escult-ink-2 leading-relaxed">{item.description}</p>
            </div>
          );
        })}
      </div>
    </PageShell>
  );
};
