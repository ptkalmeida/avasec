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
  onBack: () => void;
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

/** Ícone e cor são decoração cíclica — não são campos editáveis. */
const DECORACOES = [
  { icon: Video, accent: '#540D6E' },
  { icon: Users, accent: '#3BCEAC' },
  { icon: Award, accent: '#EE4266' }
];

export const AvaPage: React.FC<AvaPageProps> = ({ onBack, content }) => {
  const items = pageItems(content, DEFAULT_ITEMS);

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Ecossistema de Qualificação Digital')}
      title={pageField(content, 'title', 'O que é o AVA?')}
      description={pageField(content, 'description', 'O AVA (Ambiente Virtual de Aprendizagem) da Escola Estadual da Cultura é um ecossistema digital inteligente voltado para a formação continuada, democrático e acessível a todos os fazedores de cultura.')}
      align="center"
      onBack={onBack}
    >
      <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
        {items.map((item, index) => {
          const deco = DECORACOES[index % DECORACOES.length];
          const Icon = deco.icon;
          return (
            <div
              key={item.id}
              className="bg-slate-50 rounded-3xl p-6.5 border border-slate-200/60 hover:shadow-lg transition-all space-y-4 text-left"
            >
              <div
                className="h-12 w-12 rounded-2xl flex items-center justify-center"
                style={{ backgroundColor: `${deco.accent}1a`, color: deco.accent }}
              >
                <Icon className="h-6 w-6" />
              </div>
              <h4 className="text-lg font-extrabold text-slate-900 font-serif">{item.title}</h4>
              <p className="text-xs text-slate-500 leading-relaxed">{item.description}</p>
            </div>
          );
        })}
      </div>
    </PageShell>
  );
};
