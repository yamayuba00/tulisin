<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Pembayaran
|--------------------------------------------------------------------------
|
| Titik tunggal untuk mengganti penyedia pembayaran. Saat ingin migrasi,
| cukup ubah `provider` (PAYMENT_PROVIDER) dan tambah class provider baru
| di bawah App\Services\Payments.
|
*/

return [
    // Nama provider aktif: sumopod (QRIS). Ganti di .env: PAYMENT_PROVIDER
    'provider' => env('PAYMENT_PROVIDER', 'sumopod'),

    // Biaya tambahan yang dibebankan ke pembeli di atas nominal koin.
    // fee_fixed = biaya tetap (Rp); fee_percent = persen dari nominal (0-100).
    'fee_fixed' => (int) env('PAYMENT_FEE_FIXED', 2000),
    'fee_percent' => (float) env('PAYMENT_FEE_PERCENT', 0),

    'currency' => env('PAYMENT_CURRENCY', 'IDR'),
    'expires_in_hours' => (int) env('PAYMENT_EXPIRES_HOURS', 24),

    // URL redirect setelah bayar / batal. Wajib URL publik yang valid
    // (localhost biasanya ditolak SumoPod). Untuk tes lokal pakai tunnel
    // (ngrok/cloudflared) lalu set di .env.
    'success_return_url' => env('PAYMENT_SUCCESS_RETURN_URL'),
    'cancel_return_url' => env('PAYMENT_CANCEL_RETURN_URL'),

    'providers' => [
        'sumopod' => [
            'sandbox' => (bool) env('SUMOPOD_SANDBOX', true),
            'sandbox_base_url' => env('SUMOPOD_SANDBOX_BASE_URL', 'https://api-pay-sandbox.sumopod.com'),
            'live_base_url' => env('SUMOPOD_LIVE_BASE_URL', 'https://api-pay.sumopod.com'),
            'sandbox_api_key' => env('SUMOPOD_SANDBOX_API_KEY'),
            'live_api_key' => env('SUMOPOD_LIVE_API_KEY'),
            'webhook_secret' => env('SUMOPOD_WEBHOOK_SECRET'),
        ],
    ],
];
