<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fonte de vídeo de uma aula, derivada por parsing de Lesson.videoUrl —
 * o provider é função pura da URL, sem coluna extra no schema (ADR 08).
 * Espelhado em src/utils/videoSource.ts; casos de teste devem manter paridade.
 */
final readonly class VideoSource
{
    private const YOUTUBE_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    private const FILE_EXTENSIONS = ['mp4', 'webm', 'ogg', 'm4v', 'mov'];

    private function __construct(
        public VideoProvider $provider,
        public string $canonicalUrl,
        public ?string $videoId,
    ) {}

    public static function tryParse(?string $url): ?self
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 2000) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return self::tryParseRelativeFile($url);
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';

        $videoId = self::extractYoutubeId($host, $path, $query);
        if ($videoId !== null) {
            return new self(
                VideoProvider::Youtube,
                "https://www.youtube.com/watch?v={$videoId}",
                $videoId,
            );
        }

        if (self::hasVideoExtension($path)) {
            return new self(VideoProvider::File, $url, null);
        }

        return null;
    }

    public static function isValid(?string $url): bool
    {
        return self::tryParse($url) instanceof self;
    }

    private static function tryParseRelativeFile(string $url): ?self
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }
        if (! str_starts_with($path, '/uploads/') || str_contains($path, '..')) {
            return null;
        }
        if (! self::hasVideoExtension($path)) {
            return null;
        }

        return new self(VideoProvider::File, $url, null);
    }

    private static function extractYoutubeId(string $host, string $path, string $query): ?string
    {
        $candidate = null;

        if ($host === 'youtu.be') {
            $candidate = ltrim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if ($path === '/watch') {
                parse_str($query, $params);
                $v = $params['v'] ?? null;
                $candidate = is_string($v) ? $v : null;
            } elseif (preg_match('#^/(?:embed|shorts|live|v)/([^/]+)$#', $path, $m) === 1) {
                $candidate = $m[1];
            }
        }

        if ($candidate === null || preg_match(self::YOUTUBE_ID_PATTERN, $candidate) !== 1) {
            return null;
        }

        return $candidate;
    }

    private static function hasVideoExtension(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, self::FILE_EXTENSIONS, true);
    }
}
