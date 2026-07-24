<?php

declare(strict_types=1);

// Configuração de upload — espelha src/server/upload.ts.
//
// IMPORTANTE (migração): por padrão o root aponta para a MESMA pasta uploads/ do Node
// (base_path('../uploads')), para que arquivos PÚBLICOS gravados pelo Laravel sejam
// servidos pela rota estática /uploads que o Node já expõe (origem única). No corte
// final isso passa a ser responsabilidade do Nginx/Laravel.
return [
    'root' => env('UPLOADS_ROOT', base_path('..'.DIRECTORY_SEPARATOR.'uploads')),

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
    'mime_by_ext' => [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
];
