<?php

declare(strict_types=1);

namespace App\Services\Midia;

use Closure;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * FFmpeg do AVASEC (ADR 023). Não conhece Laravel, banco nem disco: recebe caminhos
 * locais e devolve arquivos locais — quem busca o original e envia o resultado ao
 * MinIO é o job.
 *
 * Os comandos são ARRAYS de argumentos, nunca uma string de shell: o caminho de entrada
 * pode vir de nome de arquivo escolhido por quem enviou, e array não passa por
 * interpretação do shell (sem injeção por `;`, `$()` ou aspas).
 */
final class TranscodificadorDeVideo
{
    /** Bitrate de vídeo por altura (kbps) — vão para o `BANDWIDTH` do master.m3u8. */
    private const BITRATE_VIDEO = [1080 => 5000, 720 => 2800, 480 => 1400, 360 => 800, 240 => 400];

    private const BITRATE_AUDIO_KBPS = 128;

    /** @var Closure(list<string>, int): void */
    private readonly Closure $executar;

    /**
     * @param  (Closure(list<string>, int): void)|null  $executar  Troca a execução real (testes).
     */
    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly int $timeout = 3600,
        ?Closure $executar = null,
    ) {
        $this->executar = $executar ?? self::executarDeVerdade(...);
    }

    /**
     * MP4 H.264/AAC com `faststart` (o `moov` no começo, para o <video> tocar antes de
     * baixar tudo). A altura nunca passa da do original.
     *
     * @return list<string>
     */
    public function comandoMp4(string $entrada, string $saida, int $altura): array
    {
        return [
            $this->ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $entrada,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-vf', $this->filtroDeEscala($altura),
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', self::BITRATE_AUDIO_KBPS.'k',
            '-movflags', '+faststart',
            $saida,
        ];
    }

    /**
     * Uma variante HLS (VOD, segmentos de 6 s) em `$pasta/index.m3u8`.
     *
     * @return list<string>
     */
    public function comandoHls(string $entrada, string $pasta, int $altura): array
    {
        return [
            $this->ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $entrada,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-vf', $this->filtroDeEscala($altura),
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            // GOP fixo de 2 s a 30 fps: os cortes de segmento caem em quadro-chave.
            '-g', '60', '-keyint_min', '60', '-sc_threshold', '0',
            '-c:a', 'aac', '-b:a', self::BITRATE_AUDIO_KBPS.'k',
            '-f', 'hls', '-hls_time', '6', '-hls_playlist_type', 'vod',
            '-hls_segment_filename', $pasta.DIRECTORY_SEPARATOR.'seg_%04d.ts',
            $pasta.DIRECTORY_SEPARATOR.'index.m3u8',
        ];
    }

    public function gerarMp4(string $entrada, string $saida, int $altura): void
    {
        ($this->executar)($this->comandoMp4($entrada, $saida, $altura), $this->timeout);

        if (! is_file($saida) || filesize($saida) === 0) {
            throw new RuntimeException('O FFmpeg terminou sem gerar o MP4.');
        }
    }

    /**
     * Gera uma variante por altura em `$pasta/<altura>p/` e o `master.m3u8` que as
     * aponta. Devolve os caminhos relativos a `$pasta`, prontos para virar chaves.
     *
     * @param  list<int>  $alturas
     * @return list<string>
     */
    public function gerarHls(string $entrada, string $pasta, array $alturas): array
    {
        $linhas = ['#EXTM3U', '#EXT-X-VERSION:3'];
        $arquivos = [];

        foreach ($alturas as $altura) {
            $sub = $pasta.DIRECTORY_SEPARATOR.$altura.'p';
            if (! is_dir($sub) && ! mkdir($sub, 0775, true) && ! is_dir($sub)) {
                throw new RuntimeException("Não foi possível criar a pasta de saída do HLS ({$altura}p).");
            }

            ($this->executar)($this->comandoHls($entrada, $sub, $altura), $this->timeout);

            if (! is_file($sub.DIRECTORY_SEPARATOR.'index.m3u8')) {
                throw new RuntimeException("O FFmpeg terminou sem gerar a variante HLS {$altura}p.");
            }

            $bandwidth = ((self::BITRATE_VIDEO[$altura] ?? 800) + self::BITRATE_AUDIO_KBPS) * 1000;
            $linhas[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth}";
            $linhas[] = "{$altura}p/index.m3u8";

            foreach (glob($sub.DIRECTORY_SEPARATOR.'*') ?: [] as $arquivo) {
                $arquivos[] = "{$altura}p/".basename($arquivo);
            }
        }

        file_put_contents($pasta.DIRECTORY_SEPARATOR.'master.m3u8', implode("\n", $linhas)."\n");
        $arquivos[] = 'master.m3u8';

        return $arquivos;
    }

    /** `min(altura, ih)` impede ampliar vídeo pequeno; `-2` mantém a largura par (exigência do H.264). */
    private function filtroDeEscala(int $altura): string
    {
        return "scale=-2:'min({$altura},ih)'";
    }

    /** @param list<string> $comando */
    private static function executarDeVerdade(array $comando, int $timeout): void
    {
        $processo = new Process($comando, null, null, null, $timeout);
        $processo->run();

        if (! $processo->isSuccessful()) {
            // Só o fim do stderr: o do FFmpeg pode ser enorme e nunca é para o usuário.
            throw new RuntimeException('FFmpeg falhou: '.mb_substr(trim($processo->getErrorOutput()), -800));
        }
    }
}
