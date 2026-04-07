<?php

$allowedOrigins = array_values(array_unique(array_filter(array_merge(
    env('FRONTEND_URL') ? [rtrim((string) env('FRONTEND_URL'), '/')] : [],
    env('CORS_ALLOWED_ORIGINS')
        ? array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS')))
        : [],
))));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins !== [] ? $allowedOrigins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
