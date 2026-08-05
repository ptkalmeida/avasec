import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { VideoPlayer } from '../../src/components/shared/VideoPlayer';

describe('VideoPlayer', () => {
  it('renderiza iframe de embed para URL do YouTube', () => {
    render(<VideoPlayer videoUrl="https://youtu.be/dQw4w9WgXcQ" title="Aula 1" />);

    const iframe = screen.getByTitle('Aula 1');
    expect(iframe.tagName).toBe('IFRAME');
    // Embed sem cookies + parâmetro origin dinâmico (ADR 08).
    const src = iframe.getAttribute('src') ?? '';
    expect(src).toContain('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
    expect(src).toContain('origin=');
  });

  it('renderiza <video> nativo para arquivo de vídeo e dispara callbacks', () => {
    const onTimeUpdate = vi.fn();
    const onEnded = vi.fn();
    const { container } = render(
      <VideoPlayer
        videoUrl="/uploads/videos/aula.mp4"
        onTimeUpdate={onTimeUpdate}
        onEnded={onEnded}
      />
    );

    const video = container.querySelector('video');
    expect(video).not.toBeNull();
    expect(video).toHaveAttribute('src', '/uploads/videos/aula.mp4');
    expect(container.querySelector('iframe')).toBeNull();

    fireEvent.timeUpdate(video!);
    fireEvent.ended(video!);
    expect(onTimeUpdate).toHaveBeenCalledTimes(1);
    expect(onEnded).toHaveBeenCalledTimes(1);
  });

  it('mostra "Vídeo indisponível" para URL inválida — sem <video> nem iframe', () => {
    const { container } = render(<VideoPlayer videoUrl="https://example.com/pagina" />);

    expect(screen.getByText(/vídeo indisponível/i)).toBeInTheDocument();
    expect(container.querySelector('video')).toBeNull();
    expect(container.querySelector('iframe')).toBeNull();
  });

  it('mostra o placeholder também sem URL nenhuma (sem fallback de vídeo demo)', () => {
    const { container } = render(<VideoPlayer />);

    expect(screen.getByText(/vídeo indisponível/i)).toBeInTheDocument();
    expect(container.querySelector('video')).toBeNull();
  });

  it('usa o unavailableSlot customizado quando fornecido', () => {
    render(
      <VideoPlayer videoUrl="" unavailableSlot={<span>Sem vídeo associado a esta aula</span>} />
    );

    expect(screen.getByText('Sem vídeo associado a esta aula')).toBeInTheDocument();
  });
});
