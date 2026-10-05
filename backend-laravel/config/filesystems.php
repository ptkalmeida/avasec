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
        | /private) — nenhum arquivo mudou de lugar, nenhuma URL mudou. No dia do
        | MinIO, troca-se o driver para `s3` aqui e migram-se os objetos; a regra
        | de negócio não muda (plano 11, item F3).
        |
        | `publico`: servido sem autenticação em /uploads/<nome> (imagens de aula,
        | material de apoio). `privado`: só por download autorizado (entregas).
        */
        'publico' => [
            'driver' => 'local',
            'root' => rtrim((string) env('UPLOADS_ROOT', base_path('..'.DIRECTORY_SEPARATOR.'uploads')), '/\\').DIRECTORY_SEPARATOR.'public',
            'visibility' => 'public',
            'throw' => true,
            'report' => false,
        ],

        'privado' => [
            'driver' => 'local',
            'root' => rtrim((string) env('UPLOADS_ROOT', base_path('..'.DIRECTORY_SEPARATOR.'uploads')), '/\\').DIRECTORY_SEPARATOR.'private',
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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
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
