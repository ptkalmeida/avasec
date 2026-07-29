<?php

declare(strict_types=1);

// Certificados — base da URL pública de verificação impressa no PDF (QR e texto).
// Em produção SPA e API compartilham a origem (Nginx), então APP_URL basta;
// a env dedicada permite divergir (ex.: domínio público != host interno da API).
return [
    'verification_base_url' => env('CERT_VERIFICATION_BASE_URL', env('APP_URL', 'http://localhost')),
];
