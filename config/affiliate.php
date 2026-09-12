<?php

// Aturan program afiliasi. Nilai sentral agar perhitungan komisi, konversi,
// dan penarikan konsisten di sisi server (tidak bisa dimanipulasi client).

return [
    // Komisi (Rupiah) untuk perujuk saat referral melakukan pembelian langganan pertama kali.
    'commission_per_referral' => 4000,

    // Nilai tukar saat penarikan komisi menjadi koin: 1 koin = Rp 250.
    'conversion_rate' => 250,

    // Minimal penarikan komisi (Rupiah).
    'min_withdrawal' => 50000,

    // Potongan (Rupiah) untuk referral saat pembelian langganan pertamanya.
    'referral_discount' => 10000,
];
