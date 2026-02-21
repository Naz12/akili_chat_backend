<?php

$defaultOrigins = [
    'https://akili.akmicroservice.com',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3001',
];

// Optional: add extra origins via env (comma-separated), e.g. CORS_ALLOWED_ORIGINS_EXTRA=https://staging.akili.example.com
$extra = env('CORS_ALLOWED_ORIGINS_EXTRA', '');
if ($extra !== '') {
    $defaultOrigins = array_merge($defaultOrigins, array_map('trim', explode(',', $extra)));
}

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],

    'allowed_origins' => array_values(array_unique($defaultOrigins)),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin', 'X-Guest-UUID', 'X-CSRF-TOKEN', 'Referer', 'User-Agent', 'post'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,
];
