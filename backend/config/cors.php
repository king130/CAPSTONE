<?php

$configured = array_values(array_unique(array_filter(array_merge(
    env('FRONTEND_URL') ? [rtrim((string) env('FRONTEND_URL'), '/')] : [],
    env('CORS_ALLOWED_ORIGINS')
        ? array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS')))
        : [],
))));

// Treat localhost and 127.0.0.1 as interchangeable for local SPA origins.
$allowedOrigins = [];
foreach ($configured as $origin) {
    $allowedOrigins[] = $origin;
    if (str_contains($origin, '://127.0.0.1')) {
        $allowedOrigins[] = str_replace('://127.0.0.1', '://localhost', $origin);
    } elseif (str_contains($origin, '://localhost')) {
        $allowedOrigins[] = str_replace('://localhost', '://127.0.0.1', $origin);
    }
}
$allowedOrigins = array_values(array_unique(array_filter($allowedOrigins)));

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
