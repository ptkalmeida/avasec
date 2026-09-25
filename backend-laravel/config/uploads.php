<?php

declare(strict_types=1);

// Configuração de upload — o que é aceito e com que tipo é servido.
//
// ONDE os arquivos ficam não mora mais aqui: são os discos `publico` e `privado` de
// config/filesystems.php (Norma TI-SECEC C.5.2), que leem o mesmo UPLOADS_ROOT de
// antes. Havia uma chave `root` aqui; saiu para não haver duas configurações da mesma
// pasta que pudessem discordar.
return [
    'max_size_mb' => (int) env('UPLOAD_MAX_SIZE_MB', 15),

    // Extensão declarada -> MIMEs reais aceitos (conferidos pelos magic bytes via finfo,
    // nunca pelo Content-Type do cliente). Para .docx, o finfo costuma detectar o
    // container ZIP (application/zip); ambos são aceitos. .doc (OLE legado) não é aceito.
    'allowed_types' => [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
    ],

    // Extensão -> MIME usado no Content-Type do download.
    // mp4/webm/ogg não passam pelo endpoint de upload (não estão em allowed_types) — só
    // servem para o Content-Type de vídeos colocados manualmente em uploads/public para
    // simular armazenamento no servidor (ADR 08, provider 'file').
    'mime_by_ext' => [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'm4v' => 'video/x-m4v',
        'mov' => 'video/quicktime',
    ],
];
