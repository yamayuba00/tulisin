<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'crossref' => [
        'base_url' => env('CROSSREF_BASE_URL', 'https://api.crossref.org'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
    ],

    // Embedding untuk RAG. DeepSeek belum menyediakan endpoint /v1/embeddings
    // per Sept 2026 (terverifikasi 404), jadi provider default tetap deepseek
    // agar saat resmi dirilis otomatis aktif; fallback lexical sudah jalan tanpa ini.
    'embedding' => [
        'provider' => env('EMBEDDING_PROVIDER', 'deepseek'),
        'api_key' => env('EMBEDDING_API_KEY', env('DEEPSEEK_API_KEY')),
        'base_url' => env('EMBEDDING_BASE_URL', env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com')),
        'model' => env('EMBEDDING_MODEL', 'deepseek-embedding'),
        'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1536),
    ],

    'google_tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID'),

];
