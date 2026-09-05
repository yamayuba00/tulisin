<?php

// Tarif kredit bawaan (fallback) untuk semua fitur yang memotong kredit.
// Nilai aktual bisa diubah super-admin lewat halaman "Pengaturan Kredit"
// dan disimpan di tabel `settings` (key: credit_pricing).

return [
    'pricing' => [
        'ai_generate' => 5,
        'ai_plagiarism' => 1,
        'ai_turnitin' => 20,
        'template' => 5,
        'font' => 4,
        'image_package_size' => 1,
        'image_package_credits' => 1,
        'download_base' => 4,
        'download_per_10_pages' => 1,
    ],
];
