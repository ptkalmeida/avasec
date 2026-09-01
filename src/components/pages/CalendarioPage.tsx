/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { Clock, ExternalLink, CalendarOff, Video, Globe } from 'lucide-react';
import { PageShell } from './PageShell';
import { SitePageContent, Course, WebinarEvent } from '../../types';
import { pageField } from '../../utils/sitePageContent';
import { features } from '../../config/features';
import {
  buildAgenda,
  formatDia,
  formatMes,
  formatHora,
  distanciaEmDias,
  AgendaEvent,
} from '../../utils/liveSchedule';

/** Janela da agenda. Também aparece no texto, para a página não prometer outra coisa. */
const DIAS_DA_AGENDA = 30;

interface CalendarioPageProps {
  onBack: () => void;
  isUserLoggedIn: boolean;
  /** Abre o modal de login quando o visitante ainda não está autenticado. */
  onRequireLogin: () => void;
  speakText: (text: string) => void;
  /** Conteúdo editado pelo admin — só o cabeçalho da página; a agenda vem dos dados. */
  content?: SitePageContent;
  /** Cursos do catálogo: as aulas ao vivo agendadas pelos gestores vêm daqui. */
  courses: Course[];
  /** Webinars globais agendados pela coordenação. */
  webinars: WebinarEvent[];
}

/** Faixa colorida à esquerda do card — decoração cíclica, não é campo editável. */
const FAIXAS = [
  'border-l-4 border-l-[#540D6E]',
  'border-l-4 border-l-[#3BCEAC]',
  'border-l-4 border-l-[#EE4266]',
];

const EventoCard: React.FC<{
  evento: AgendaEvent;
  faixa: string;
  agora: Date;
  isUserLoggedIn: boolean;
  onParticipar: () => void;
}> = ({ evento, faixa, agora, isUserLoggedIn, onParticipar }) => (
  <div
    className={`bg-slate-50 rounded-2xl p-5 border border-slate-200/60 ${faixa} hover:shadow-md transition-all flex flex-col justify-between text-left h-full`}
  >
    <div className="space-y-4">
      <div className="flex justify-between items-center gap-2">
        <span className="text-[9px] font-mono font-black uppercase bg-white border border-slate-200 text-slate-600 px-2.5 py-1 rounded-md inline-flex items-center gap-1.5">
          {evento.kind === 'webinar'
            ? <><Globe className="h-3 w-3" /> Webinar aberto</>
            : <><Video className="h-3 w-3" /> Aula ao vivo</>}
        </span>
        <span className="text-[10px] font-mono text-slate-450 flex items-center gap-1 shrink-0">
          <Clock className="h-3.5 w-3.5" />
          {formatHora(evento.quando)}
        </span>
      </div>

      <div className="flex gap-4 items-start">
        <div className="h-14 w-14 shrink-0 bg-white rounded-xl border border-slate-200 flex flex-col items-center justify-center font-mono">
          <span className="text-xl font-black text-[#540D6E] leading-none">{formatDia(evento.quando)}</span>
          <span className="text-[9px] text-slate-400 font-extrabold">{formatMes(evento.quando)}</span>
        </div>
        <div className="space-y-1">
          <strong className="text-xs font-bold text-slate-800 line-clamp-2 leading-snug">{evento.titulo}</strong>
          <span className="text-[11px] text-slate-550 block">{evento.contexto}</span>
        </div>
      </div>
    </div>

    <div className="pt-4 mt-4 border-t border-slate-200/50 flex items-center justify-between gap-2">
      <span className="text-[10px] font-bold text-slate-450 uppercase tracking-wide">
        {distanciaEmDias(evento.quando, agora)}
        {evento.durationMinutes !== null && ` · ${evento.durationMinutes} min`}
      </span>
      {/* O link da sala NÃO é exibido aqui: esta página é pública, e o catálogo
          anônimo já vem sem meetingLink. Quem tem acesso entra pelo painel. */}
      <button
        onClick={onParticipar}
        className="text-[10px] font-black uppercase text-[#540D6E] hover:text-purple-950 flex items-center gap-1 cursor-pointer shrink-0"
      >
        <span>{isUserLoggedIn ? 'Ir ao painel' : 'Participar'}</span>
        <ExternalLink className="h-3.5 w-3.5" />
      </button>
    </div>
  </div>
);

export const CalendarioPage: React.FC<CalendarioPageProps> = ({
  onBack,
  isUserLoggedIn,
  onRequireLogin,
  speakText,
  content,
  courses,
  webinars,
}) => {
  // Um único "agora" por render: usar new Date() dentro do map faria dois cards da
  // mesma lista comparados a instantes diferentes.
  const agora = React.useMemo(() => new Date(), [courses, webinars]);

  const agenda = React.useMemo(
    () => buildAgenda(courses, features.eventosWebinars ? webinars : [], agora, DIAS_DA_AGENDA),
    [courses, webinars, agora]
  );

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Encontros Síncronos Interativos')}
      title={pageField(content, 'title', 'Calendário de Aulas ao Vivo')}
      description={pageField(
        content,
        'description',
        `Nossos cursos livres oferecem encontros ao vivo periódicos para tirar dúvidas, realizar mentorias de projetos e debater temas contemporâneos da cultura. Veja o que está agendado para os próximos ${DIAS_DA_AGENDA} dias:`
      )}
      align="center"
      onBack={onBack}
    >
      {agenda.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-250 bg-slate-50/60 p-10 text-center">
          <CalendarOff className="h-8 w-8 text-slate-300 mx-auto mb-3" />
          <strong className="block text-sm font-bold text-slate-700">
            Nenhum encontro agendado para os próximos {DIAS_DA_AGENDA} dias.
          </strong>
          <span className="mt-1 block text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
            Esta agenda mostra apenas encontros realmente marcados pelos gestores dos
            cursos. Assim que uma nova aula ao vivo for agendada, ela aparece aqui.
          </span>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {agenda.map((evento, index) => (
              <EventoCard
                key={`${evento.kind}-${evento.id}`}
                evento={evento}
                faixa={FAIXAS[index % FAIXAS.length]}
                agora={agora}
                isUserLoggedIn={isUserLoggedIn}
                onParticipar={() => {
                  if (isUserLoggedIn) {
                    speakText('O link da sala fica no seu painel de estudos, na aula correspondente.');
                  } else {
                    onRequireLogin();
                    speakText('Acesso restrito. Faça login para participar dos encontros ao vivo.');
                  }
                }}
              />
            ))}
          </div>
          <p className="mt-6 text-center text-[11px] text-slate-450">
            {agenda.length} {agenda.length === 1 ? 'encontro' : 'encontros'} nos próximos {DIAS_DA_AGENDA} dias.
            O link de acesso fica disponível no painel de quem está matriculado.
          </p>
        </>
      )}
    </PageShell>
  );
};
