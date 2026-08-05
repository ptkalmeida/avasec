import React from 'react';
import { Youtube, CheckCircle2, AlertCircle } from 'lucide-react';
import { parseVideoSource } from '../../utils/videoSource';
import { VideoPlayer } from './VideoPlayer';

interface LessonVideoFieldProps {
  value: string;
  onChange: (value: string) => void;
}

/**
 * Campo de vídeo pensado para quem gerencia a plataforma sem ser da área de TI:
 * cola o link do YouTube e o vídeo aparece embedado na hora, com uma mensagem
 * clara de "deu certo" ou "esse link não serve". Reaproveita o mesmo parser e
 * player usados pelo aluno (ADR 08), então o que se vê na prévia é o que o aluno
 * verá.
 */
export const LessonVideoField: React.FC<LessonVideoFieldProps> = ({ value, onChange }) => {
  const trimmed = value.trim();
  const source = parseVideoSource(trimmed);
  const hasInput = trimmed.length > 0;

  return (
    <div>
      <label className="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider flex items-center gap-1.5">
        <Youtube className="h-3.5 w-3.5 text-red-500" />
        Vídeo da aula (opcional)
      </label>
      <p className="text-[11px] text-slate-500 mb-2 leading-relaxed">
        Abra o vídeo no YouTube, copie o endereço que aparece na barra do navegador e cole aqui.
        O vídeo aparece automaticamente abaixo — sem precisar de mais nada.
      </p>
      <input
        type="text"
        inputMode="url"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder="Cole o link do YouTube aqui (ex.: https://youtu.be/...)"
        className={`w-full rounded-lg border p-2.5 text-sm font-semibold text-slate-800 transition-colors ${
          !hasInput
            ? 'border-slate-200'
            : source
              ? 'border-emerald-300 bg-emerald-50/40'
              : 'border-amber-300 bg-amber-50/40'
        }`}
      />

      {hasInput && source && (
        <div className="mt-1.5 flex items-center gap-1.5 text-[11px] font-bold text-emerald-700">
          <CheckCircle2 className="h-3.5 w-3.5" />
          <span>Link reconhecido! Veja a prévia do vídeo abaixo.</span>
        </div>
      )}
      {hasInput && !source && (
        <div className="mt-1.5 flex items-start gap-1.5 text-[11px] font-semibold text-amber-700">
          <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-px" />
          <span>
            Não reconhecemos esse link. Copie o endereço direto do vídeo no YouTube — ele começa
            com <strong>https://www.youtube.com</strong> ou <strong>https://youtu.be</strong>.
          </span>
        </div>
      )}

      {source && (
        <div className="mt-3">
          <span className="text-[10px] uppercase font-black text-slate-400 tracking-wider block mb-1.5">
            Prévia (é assim que o aluno vai ver)
          </span>
          <div className="aspect-video w-full max-w-md rounded-xl overflow-hidden bg-black border border-slate-800 shadow-sm">
            <VideoPlayer videoUrl={trimmed} title="Prévia do vídeo da aula" />
          </div>
        </div>
      )}
    </div>
  );
};
