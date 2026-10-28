<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        /*
        | Arquivos enviados pelo AVASEC (Norma TI-SECEC C.5.2 e C.5.3): tudo passa
        | pelo Laravel Filesystem, nunca por file_put_contents. Hoje os dois discos são
        | locais e apontam para as MESMAS pastas de antes (UPLOADS_ROOT/public e
        | /private) — nenhum arquivo mudou de lugar, nenhuma URL mudou. Com
        | UPLOADS_DISK_DRIVER=s3 os dois passam para um bucket só (UPLOADS_BUCKET),
        | cada um sob seu prefixo (`publico/` e `privado/`); o endpoint e as chaves
        | são os AWS_* de sempre. A URL pública continua /uploads/<nome>: muda onde o
        | arquivo mora, não o endereço (plano 11, item F3). Não é o disco `midia`: o
        | vídeo próprio tem a chave dele (MEDIA_DISK_DRIVER) e pode ficar desligado.
        |
        | `publico`: servido sem autenticação em /uploads/<nome> (imagens de aula,
        | material de apoio). `privado`: só por download autorizado (entregas).
        | No S3 os dois são `private` de propósito: o público sai pelo app, nunca por
        | ACL ou bucket aberto.
        */
        'publico' => env('UPLOADS_DISK_DRIVER', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'bucket' => env('UPLOADS_BUCKET'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true),
                'root' => 'publico',
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => rtrim((string) env('UPLOADS_ROOT', base_path('..'.DIRECTORY_SEPARATOR.'uploads')), '/\\').DIRECTORY_SEPARATOR.'public',
                'visibility' => 'public',
                'throw' => true,
                'report' => false,
            ],

        'privado' => env('UPLOADS_DISK_DRIVER', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'bucket' => env('UPLOADS_BUCKET'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true),
                'root' => 'privado',
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => rtrim((string) env('UPLOADS_ROOT', base_path('..'.DIRECTORY_SEPARATOR.'uploads')), '/\\').DIRECTORY_SEPARATOR.'private',
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ],

        /*
        | `midia`: original e derivados de vídeo (ADR 023). Local por padrão, na pasta
        | `midia` ao lado de public/ e private/ — que só passa a existir se o recurso
        | for ligado. `MEDIA_DISK_DRIVER=s3` aponta o mesmo disco para o MinIO, sem
        | mudar uma linha de código (as chaves guardadas são relativas ao disco).
        */
        'midia' => env('MEDIA_DISK_DRIVER', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'bucket' => env('MEDIA_BUCKET'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true),
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => rtrim((string) env('UPLOADS_ROOT', base_path('..'.DIRECTORY_SEPARATOR.'uploads')), '/\\').DIRECTORY_SEPARATOR.'midia',
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
