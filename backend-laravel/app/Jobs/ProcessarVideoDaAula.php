<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Aula;
use App\Services\Midia\TranscodificadorDeVideo;
use App\Support\MidiaPropria;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Transcodifica o vídeo ORIGINAL de uma aula e publica o resultado (ADR 023).
 *
 * Recurso DESLIGADO por padrão: com `MEDIA_DRIVER=youtube` o job sai na primeira linha
 * sem tocar em disco, banco ou FFmpeg.
 *
 * Fluxo: baixa o original do disco `midia` para uma pasta temporária -> FFmpeg gera o
 * MP4 (e o HLS, se ligado) -> envia ao disco numa chave NOVA -> só então grava
 * Aula.url_video. Quem lê a aula nunca vê um vídeo pela metade, e uma falha deixa a
 * aula exatamente como estava.
 *
 * ADR 12: o original nunca é apagado nem sobrescrito, e cada execução grava numa
 * pasta própria (timestamp + aleatório). O que a pasta temporária perde é cópia de
 * trabalho, não registro. O url_video antigo (o link do YouTube, por exemplo) fica no
 * histórico de versões da aula, que o próprio model grava ao atualizar.
 */
final class ProcessarVideoDaAula implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /** Espera entre tentativas, em segundos. */
    public int $backoff = 120;

    /**
     * Segundos. Vale por cima do `--timeout=90` do worker padrão (o do job prevalece);
     * o `retry_after` da conexão da fila precisa ficar acima dele (ADR 023).
     */
    public int $timeout = 3600;

    public function __construct(
        public readonly string $aula_id,
        /** Chave do original no disco `midia`. */
        public readonly string $chaveOriginal,
    ) {
        $this->timeout = MidiaPropria::timeout();
        $this->onQueue(MidiaPropria::fila());
    }

    public function handle(?TranscodificadorDeVideo $transcodificador = null): void
    {
        if (! MidiaPropria::ativa()) {
            return;
        }

        $base = MidiaPropria::urlPublica();
        if ($base === '') {
            throw new RuntimeException('MEDIA_PUBLIC_URL não está definido: sem ele não há endereço para gravar em Aula.url_video (ADR 023).');
        }

        $aula = Aula::query()->find($this->aula_id);
        if ($aula === null) {
            Log::warning('midia.aula_inexistente', ['aula_id' => $this->aula_id]);

            return;
        }

        $disco = Storage::disk(MidiaPropria::disco());
        if (! $disco->exists($this->chaveOriginal)) {
            throw new RuntimeException("Original não encontrado no disco de mídia: {$this->chaveOriginal}");
        }

        $transcodificador ??= new TranscodificadorDeVideo(
            MidiaPropria::ffmpeg(),
            MidiaPropria::timeout(),
        );

        $trabalho = sys_get_temp_dir().DIRECTORY_SEPARATOR.'avasec-midia-'.bin2hex(random_bytes(8));
        mkdir($trabalho, 0775, true);

        try {
            $original = $trabalho.DIRECTORY_SEPARATOR.'original.'.pathinfo($this->chaveOriginal, PATHINFO_EXTENSION);
            $this->baixar($disco, $this->chaveOriginal, $original);

            $alturas = MidiaPropria::alturas();
            $mp4 = $trabalho.DIRECTORY_SEPARATOR.'video.mp4';
            $transcodificador->gerarMp4($original, $mp4, $alturas[0] ?? 720);

            $pasta = 'aulas/'.$this->aula_id.'/'.CarbonImmutable::now()->getTimestampMs().'-'.bin2hex(random_bytes(4));
            $this->enviar($disco, $mp4, $pasta.'/video.mp4');

            if (MidiaPropria::geraHls()) {
                $hls = $trabalho.DIRECTORY_SEPARATOR.'hls';
                mkdir($hls, 0775, true);
                foreach ($transcodificador->gerarHls($original, $hls, $alturas) as $relativo) {
                    $this->enviar($disco, $hls.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativo), $pasta.'/hls/'.$relativo);
                }
            }

            // Por último: só aqui a aula passa a apontar para o novo vídeo.
            $aula->update(['url_video' => $base.'/'.$pasta.'/video.mp4']);
        } finally {
            File::deleteDirectory($trabalho);
        }
    }

    public function failed(Throwable $erro): void
    {
        Log::error('midia.processamento_falhou', [
            'aula_id' => $this->aula_id,
            'chaveOriginal' => $this->chaveOriginal,
            'erro' => $erro->getMessage(),
        ]);
    }

    private function baixar(Filesystem $disco, string $chave, string $destino): void
    {
        $origem = $disco->readStream($chave);
        $saida = fopen($destino, 'wb');
        if ($origem === null || $saida === false) {
            throw new RuntimeException("Não foi possível baixar o original: {$chave}");
        }

        try {
            stream_copy_to_stream($origem, $saida);
        } finally {
            fclose($origem);
            fclose($saida);
        }
    }

    private function enviar(Filesystem $disco, string $arquivoLocal, string $chave): void
    {
        $fluxo = fopen($arquivoLocal, 'rb');
        if ($fluxo === false) {
            throw new RuntimeException("Não foi possível ler o arquivo gerado: {$arquivoLocal}");
        }

        try {
            $disco->writeStream($chave, $fluxo);
        } finally {
            fclose($fluxo);
        }
    }
}
