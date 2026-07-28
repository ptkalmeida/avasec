import React from 'react';
import { VideoOff } from 'lucide-react';
import { parseVideoSource } from '../../utils/videoSource';

interface VideoPlayerProps {
  videoUrl?: string | null;
  title?: string;
  className?: string;
  /** Props abaixo valem apenas para o provider 'file' (<video> nativo). */
  videoRef?: React.Ref<HTMLVideoElement>;
  autoPlay?: boolean;
  controls?: boolean;
  playbackRate?: number;
  onTimeUpdate?: (currentTime: number) => void;
  onLoadedMetadata?: (duration: number) => void;
  onEnded?: () => void;
  /** Fallback customizado quando a URL é inválida/ausente. */
  unavailableSlot?: React.ReactNode;
}

/**
 * Player único da plataforma — decide o elemento de mídia pelo provider derivado
 * da URL (ADR 08). YouTube → iframe embed; arquivo → <video> nativo.
 * Futuro provider 'hls' (vídeo no servidor): adicionar branch aqui, junto com o
 * reconhecimento de .m3u8 em utils/videoSource.ts.
 */
export const VideoPlayer: React.FC<VideoPlayerProps> = ({
  videoUrl,
  title = 'Vídeo da aula',
  className = 'w-full h-full',
  videoRef,
  autoPlay,
  controls,
  playbackRate,
  onTimeUpdate,
  onLoadedMetadata,
  onEnded,
  unavailableSlot,
}) => {
  const source = parseVideoSource(videoUrl);

  if (!source) {
    return (
      <>
        {unavailableSlot ?? (
          <div className="w-full h-full bg-slate-950 flex flex-col items-center justify-center gap-2 text-slate-500">
            <VideoOff className="h-10 w-10 text-slate-600" />
            <span className="text-xs font-semibold">Vídeo indisponível</span>
          </div>
        )}
      </>
    );
  }

  if (source.provider === 'youtube') {
    return (
      <iframe
        className={className}
        src={source.embedUrl}
        title={title}
        frameBorder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowFullScreen
      ></iframe>
    );
  }

  return (
    <video
      ref={videoRef}
      autoPlay={autoPlay}
      controls={controls}
      playsInline
      className={`${className} object-contain bg-black`}
      src={source.url}
      onEnded={onEnded}
      onTimeUpdate={(e) => onTimeUpdate?.(e.currentTarget.currentTime)}
      onLoadedMetadata={(e) => {
        onLoadedMetadata?.(e.currentTarget.duration);
        if (playbackRate) {
          e.currentTarget.playbackRate = playbackRate;
        }
      }}
    />
  );
};
