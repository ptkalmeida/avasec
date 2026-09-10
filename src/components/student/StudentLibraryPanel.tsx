import React from 'react';
import { ArrowLeft, FileText, Globe, Download, ExternalLink } from 'lucide-react';
import { useLMS } from '../../context/LMSContext';
import { safeHref } from '../../utils/safeUrl';

interface StudentLibraryPanelProps {
  onBack: () => void;
}

export const StudentLibraryPanel: React.FC<StudentLibraryPanelProps> = ({ onBack }) => {
  const { libraryItems } = useLMS();

  return (
    <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6 text-left">
      <div className="text-left">
        <button
          onClick={onBack}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-sobretitulo uppercase border border-slate-200/65"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          <span>Voltar ao Meu Painel de Estudos</span>
        </button>
      </div>
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-xl font-black text-slate-900 tracking-tight">Biblioteca Digital</h3>
          <p className="text-xs text-escult-ink-2">Repositório centralizado de materiais complementares e e-books.</p>
        </div>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        {libraryItems.map((item, idx) => (
          <div key={`${item.id}-${idx}`} className="bg-white border border-slate-200 rounded-2xl p-5 hover:border-teal-200 hover:shadow-lg transition-all group">
            <div className="flex items-start justify-between mb-4">
              <div className={`p-2.5 rounded-xl ${item.type === 'pdf' ? 'bg-rose-50 text-rose-600' : 'bg-escult-surface text-escult-purple'}`}>
                {item.type === 'pdf' ? <FileText className="h-6 w-6" /> : <Globe className="h-6 w-6" />}
              </div>
              <span className="text-sobretitulo uppercase text-escult-ink-2 bg-slate-50 px-2 py-0.5 rounded border border-slate-100">{item.category}</span>
            </div>
            <h4 className="font-bold text-sm text-slate-900 leading-tight group-hover:text-teal-700 transition-colors">{item.title}</h4>
            <p className="text-rotulo text-escult-ink-2 mt-2 leading-relaxed line-clamp-2">{item.description}</p>
            <a
              href={safeHref(item.url)}
              target="_blank"
              rel="noreferrer"
              className="mt-4 w-full bg-slate-50 hover:bg-teal-600 hover:text-white text-slate-600 font-bold py-2 rounded-xl text-apoio transition-all flex items-center justify-center gap-2 border border-slate-100"
            >
              {item.type === 'pdf' ? <Download className="h-3 w-3" /> : <ExternalLink className="h-3 w-3" />}
              <span>{item.type === 'pdf' ? 'Baixar Arquivo' : 'Acessar Link'}</span>
            </a>
          </div>
        ))}
      </div>
    </div>
  );
};
