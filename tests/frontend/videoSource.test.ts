import { describe, it, expect } from 'vitest';
import { parseVideoSource } from '../../src/utils/videoSource';

// Paridade manual com backend-laravel/tests/Unit/VideoSourceTest.php — ao alterar
// um caso aqui, replicar lá (mesmo pacto de features.ts ↔ features.php).

const CANONICAL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
const EMBED = 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=0&rel=0&modestbranding=1';

describe('parseVideoSource — URLs do YouTube canonicalizam para watch', () => {
  const cases: [string, string][] = [
    ['watch', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    ['watch sem www', 'https://youtube.com/watch?v=dQw4w9WgXcQ'],
    ['watch mobile', 'https://m.youtube.com/watch?v=dQw4w9WgXcQ'],
    ['watch com playlist e timestamp', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG&t=90'],
    ['youtu.be', 'https://youtu.be/dQw4w9WgXcQ'],
    ['youtu.be com tracking', 'https://youtu.be/dQw4w9WgXcQ?si=abc123'],
    ['embed', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
    ['shorts', 'https://www.youtube.com/shorts/dQw4w9WgXcQ'],
    ['live', 'https://www.youtube.com/live/dQw4w9WgXcQ'],
    ['v legado', 'https://www.youtube.com/v/dQw4w9WgXcQ'],
    ['nocookie', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['http', 'http://www.youtube.com/watch?v=dQw4w9WgXcQ'],
  ];

  it.each(cases)('%s', (_label, url) => {
    expect(parseVideoSource(url)).toEqual({
      provider: 'youtube',
      videoId: 'dQw4w9WgXcQ',
      embedUrl: EMBED,
      canonicalUrl: CANONICAL,
    });
  });
});

describe('parseVideoSource — arquivos de vídeo são aceitos como provider file', () => {
  const cases: [string, string][] = [
    ['mp4 absoluto', 'https://cdn.example.com/aulas/intro.mp4'],
    ['webm em uploads', '/uploads/videos/aula-01.webm'],
    ['mp4 em uploads', '/uploads/abc123.mp4'],
    ['mov absoluto', 'https://files.example.com/a.mov'],
    ['mp4 com querystring', 'https://cdn.example.com/a.mp4?token=xyz'],
  ];

  it.each(cases)('%s', (_label, url) => {
    expect(parseVideoSource(url)).toEqual({ provider: 'file', url });
  });
});

describe('parseVideoSource — URLs inválidas são rejeitadas', () => {
  const cases: [string, string | null | undefined][] = [
    ['null', null],
    ['undefined', undefined],
    ['vazio', ''],
    ['whitespace', '   '],
    ['id curto', 'https://www.youtube.com/watch?v=abc'],
    ['id longo', 'https://youtu.be/dQw4w9WgXcQextra'],
    ['playlist sem v', 'https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG'],
    ['canal', 'https://www.youtube.com/@canal'],
    ['javascript', 'javascript:alert(1)'],
    ['data uri', 'data:text/html,<script>alert(1)</script>'],
    ['ftp', 'ftp://example.com/a.mp4'],
    ['pagina sem extensao', 'https://example.com/pagina'],
    ['pdf em uploads', '/uploads/apostila.pdf'],
    ['relativo fora de uploads', '/etc/passwd'],
    ['traversal em uploads', '/uploads/../.env.mp4'],
    ['host youtube falso', 'https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ'],
  ];

  it.each(cases)('%s', (_label, url) => {
    expect(parseVideoSource(url)).toBeNull();
  });
});
