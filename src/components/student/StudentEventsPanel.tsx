import React from 'react';
import { ArrowLeft, Calendar, Clock } from 'lucide-react';
import { useLMS } from '../../context/LMSContext';

interface StudentEventsPanelProps {
  onBack: () => void;
}

export const StudentEventsPanel: React.FC<StudentEventsPanelProps> = ({ onBack }) => {
  const { webinarEvents } = useLMS();

  return (
    <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6 text-left">
      <div className="text-left">
        <button
          onClick={onBack}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-xs font-black uppercase tracking-wider border border-slate-200/65"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          <span>Voltar ao Meu Painel de Estudos</span>
        </button>
      </div>
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-xl font-black text-slate-900 tracking-tight">📅 Eventos & Webinars</h3>
          <p className="text-xs text-slate-500">Aulas magnas, workshops e eventos extracurriculares exclusivos.</p>
        </div>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {webinarEvents.map((event, idx) => (
          <div key={`${event.id}-${idx}`} className="bg-white border border-slate-200 rounded-3xl overflow-hidden hover:shadow-xl transition-all group flex flex-col sm:flex-row">
            <div className="sm:w-40 h-40 sm:h-full relative shrink-0 overflow-hidden">
              <img src={event.image} alt={event.title} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" referrerPolicy="no-referrer" />
              <div className="absolute inset-0 bg-[#540D6E]/20" />
            </div>
            <div className="p-6 flex-1 flex flex-col justify-between">
              <div>
                <div className="flex items-center gap-3 text-[10px] font-black text-teal-600 uppercase tracking-widest mb-2">
                  <span className="flex items-center gap-1"><Calendar className="h-3 w-3" /> {event.date}</span>
                  <span className="flex items-center gap-1"><Clock className="h-3 w-3" /> {event.time}</span>
                </div>
                <h4 className="font-black text-lg text-slate-900 leading-tight mb-2">{event.title}</h4>
                <p className="text-xs text-slate-500 leading-relaxed line-clamp-2">{event.description}</p>
              </div>
              <button className="mt-4 w-full bg-[#540D6E] hover:bg-slate-900 text-white font-black py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all cursor-pointer">
                Realizar Inscrição
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
