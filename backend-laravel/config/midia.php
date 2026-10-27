<?php

declare(strict_types=1);

// Mídia própria (vídeo hospedado pelo AVASEC) — DESLIGADA por padrão (ADR 023).
//
// Hoje o vídeo da aula é um link do YouTube (Lesson.videoUrl) e nada aqui roda.
// `MEDIA_DRIVER=minio` é o interruptor: só com ele o job ProcessarVideoDaAula
// trabalha. Com `youtube` (o padrão) o job sai sem tocar em nada.
return [
    // youtube | minio
    'driver' => env('MEDIA_DRIVER', 'youtube'),

    // Disco de config/filesystems.php onde ficam o original e os derivados.
    'disco' => env('MEDIA_DISK', 'midia'),

    // Base pública de onde o navegador lê os derivados (bucket do MinIO ou CDN),
    // sem barra no fim. É a decisão de ENTREGA, que ficou para o dia em que a flag
    // for ligada (ADR 023): sem ela o job se recusa a rodar em vez de gravar em
    // Lesson.videoUrl um endereço que ninguém sabe servir.
    'url_publica' => rtrim((string) env('MEDIA_PUBLIC_URL', ''), '/'),

    'ffmpeg' => env('FFMPEG_BIN', 'ffmpeg'),

    // Segundos. Precisa ficar ABAIXO do retry_after da fila `midia`, senão o job
    // é entregue de novo enquanto ainda roda.
    'timeout' => (int) env('MEDIA_TIMEOUT', 3600),

    // Fila própria: o worker atual (`queue:work` na fila `default`, timeout 90 s)
    // nunca a consome — é o que mantém o comportamento de hoje intacto.
    'fila' => env('MEDIA_QUEUE', 'midia'),

    // Também gera HLS (várias resoluções)? Só a entrega do MP4 existe hoje; o HLS
    // espera a decisão de player e de URLs assinadas (ADR 023).
    'hls' => (bool) env('MEDIA_HLS', false),

    // Alturas das variantes HLS e do MP4 (a maior vira o MP4). Nunca amplia: a
    // altura é limitada à do original.
    'alturas' => [720, 480, 360],

    'max_size_mb' => (int) env('MEDIA_MAX_SIZE_MB', 500),

    // Extensão -> MIMEs reais aceitos para o ORIGINAL (conferidos por magic bytes).
    'tipos_originais' => [
        'mp4' => ['video/mp4'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'mov' => ['video/quicktime'],
        'webm' => ['video/webm'],
    ],
];
