/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { Clock, ExternalLink } from 'lucide-react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems } from '../../utils/sitePageContent';

interface CalendarioPageProps {
  onBack: () => void;
  isUserLoggedIn: boolean;
  /** Abre o modal de login quando o visitante ainda não está autenticado. */
  onRequireLogin: () => void;
  speakText: (text: string) => void;
  /** Conteúdo editado pelo admin; ausente = usa os padrões abaixo. */
  content?: SitePageContent;
}

/** Padrão de fábrica, usado quando a API não respondeu (espelha o backend). */
const DEFAULT_ITEMS: SitePageItem[] = [
  {
    id: 'evento-1',
    day: '10',
    month: 'JUL',
    time: '19:00',
    title: 'Mentoria de Elaboração de Editais (Lei Paulo Gustavo)',
    tutor: 'Profª Helena Ribeiro',
    type: 'Sessão Aberta'
  },
  {
    id: 'evento-2',
    day: '15',
    month: 'JUL',
    time: '18:30',
    title: 'Aula Prática: Fotografia Digital Básica com Smartphones',
    tutor: 'Prof. Marcos Souza',
    type: 'Exclusivo de Trilha'
  },
  {
    id: 'evento-3',
    day: '22',
    month: 'JUL',
    time: '20:00',
    title: 'Fórum Geral: Elaboração e Gestão de Projetos Culturais',
    tutor: 'Prof. Daniel Costa',
    type: 'Aberto a Todos'
  }
];

/** Faixa colorida à esquerda do card — decoração cíclica, não é campo editável. */
const FAIXAS = [
  'border-l-4 border-l-[#540D6E]',
  'border-l-4 border-l-[#3BCEAC]',
  'border-l-4 border-l-[#EE4266]'
];

export const CalendarioPage: React.FC<CalendarioPageProps> = ({
  onBack,
  isUserLoggedIn,
  onRequireLogin,
  speakText,
  content,
}) => {
  const items = pageItems(content, DEFAULT_ITEMS);

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Encontros Síncronos Interativos')}
      title={pageField(content, 'title', 'Calendário de Aulas ao Vivo')}
      description={pageField(content, 'description', 'Nossos cursos livres oferecem encontros ao vivo periódicos para tirar dúvidas, realizar mentorias de projetos e debater temas contemporâneos da cultura. Acompanhe a nossa agenda síncrona:')}
      align="center"
      onBack={onBack}
    >
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {items.map((evt, index) => (
          <div
            key={evt.id}
            className={`bg-slate-50 rounded-2xl p-5 border border-slate-200/60 ${FAIXAS[index % FAIXAS.length]} hover:shadow-md transition-all flex flex-col justify-between text-left h-full`}
          >
            <div className="space-y-4">
              <div className="flex justify-between items-center gap-2">
                <span className="text-[9px] font-mono font-black uppercase bg-white border border-slate-200 text-slate-600 px-2.5 py-1 rounded-md">
                  {evt.type}
                </span>
                <span className="text-[10px] font-mono text-slate-450 flex items-center gap-1 shrink-0">
                  <Clock className="h-3.5 w-3.5" />
                  {evt.time}
                </span>
              </div>

              <div className="flex gap-4 items-start">
                <div className="h-14 w-14 shrink-0 bg-white rounded-xl border border-slate-200 flex flex-col items-center justify-center font-mono">
                  <span className="text-xl font-black text-[#540D6E] leading-none">{evt.day}</span>
                  <span className="text-[9px] text-slate-400 font-extrabold">{evt.month}</span>
                </div>
                <div className="space-y-1">
                  <strong className="text-xs font-bold text-slate-800 line-clamp-2 leading-snug">{evt.title}</strong>
                  <span className="text-[11px] text-slate-550 block">Ministrado por: {evt.tutor}</span>
                </div>
              </div>
            </div>

            <div className="pt-4 mt-4 border-t border-slate-200/50 flex items-center justify-between">
              <button
                onClick={() => {
                  if (isUserLoggedIn) {
                    speakText('Transmissão iniciará em breve! O link ficará ativo no seu painel de estudos.');
                  } else {
                    onRequireLogin();
                    speakText('Acesso restrito. Faça login para participar das mentorias.');
                  }
                }}
                className="text-[10px] font-black uppercase text-[#540D6E] hover:text-purple-950 flex items-center gap-1 cursor-pointer"
              >
                <span>Participar Aula</span>
                <ExternalLink className="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        ))}
      </div>
    </PageShell>
  );
};
