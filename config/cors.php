<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Batasi origin yang boleh mengakses API. SPA yang disajikan dari domain
    | yang sama tidak membutuhkan CORS; membiarkan daftar ini kosong berarti
    | menolak semua permintaan cross-origin.
    |
    | Bila frontend terpisah (origin berbeda), isi lewat .env, contoh:
    |   CORS_ALLOWED_ORIGINS=https://app.domain.com,https://www.domain.com
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Cookie session Sanctum (SPA) membutuhkan credentials.
    'supports_credentials' => true,
];
