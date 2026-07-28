// Fonte de vídeo de uma aula, derivada por parsing de Lesson.videoUrl (ADR 08).
// Espelho de backend-laravel/app/Support/VideoSource.php — os casos de teste em
// tests/frontend/videoSource.test.ts mantêm paridade com tests/Unit/VideoSourceTest.php.

export type VideoSource =
  | { provider: 'youtube'; videoId: string; embedUrl: string; canonicalUrl: string }
  | { provider: 'file'; url: string };
// Futuro provider 'hls' (vídeo no servidor): reconhecer extensão .m3u8 aqui e
// adicionar o branch correspondente no VideoPlayer — sem mudança de schema.

const YOUTUBE_ID_PATTERN = /^[A-Za-z0-9_-]{11}$/;
const YOUTUBE_HOSTS = new Set([
  'youtube.com',
  'www.youtube.com',
  'm.youtube.com',
  'youtube-nocookie.com',
  'www.youtube-nocookie.com',
]);
const FILE_EXTENSIONS = new Set(['mp4', 'webm', 'ogg', 'm4v', 'mov']);

export function parseVideoSource(url: string | null | undefined): VideoSource | null {
  if (!url) return null;
  const trimmed = url.trim();
  if (trimmed === '' || trimmed.length > 2000) return null;

  if (trimmed.startsWith('/')) {
    return parseRelativeFile(trimmed);
  }

  let parsed: URL;
  try {
    parsed = new URL(trimmed);
  } catch {
    return null;
  }
  if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return null;

  const videoId = extractYouTubeId(parsed);
  if (videoId) {
    return {
      provider: 'youtube',
      videoId,
      embedUrl: `https://www.youtube.com/embed/${videoId}?autoplay=0&rel=0&modestbranding=1`,
      canonicalUrl: `https://www.youtube.com/watch?v=${videoId}`,
    };
  }

  if (hasVideoExtension(parsed.pathname)) {
    return { provider: 'file', url: trimmed };
  }

  return null;
}

function parseRelativeFile(url: string): VideoSource | null {
  const path = url.split(/[?#]/, 1)[0];
  if (!path.startsWith('/uploads/') || path.includes('..')) return null;
  if (!hasVideoExtension(path)) return null;
  return { provider: 'file', url };
}

function extractYouTubeId(parsed: URL): string | null {
  const host = parsed.hostname.toLowerCase();
  let candidate: string | null = null;

  if (host === 'youtu.be') {
    candidate = parsed.pathname.replace(/^\//, '');
  } else if (YOUTUBE_HOSTS.has(host)) {
    if (parsed.pathname === '/watch') {
      candidate = parsed.searchParams.get('v');
    } else {
      const match = parsed.pathname.match(/^\/(?:embed|shorts|live|v)\/([^/]+)$/);
      candidate = match ? match[1] : null;
    }
  }

  return candidate && YOUTUBE_ID_PATTERN.test(candidate) ? candidate : null;
}

function hasVideoExtension(path: string): boolean {
  const ext = path.split('.').pop()?.toLowerCase() ?? '';
  return path.includes('.') && FILE_EXTENSIONS.has(ext);
}
