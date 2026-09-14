<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Masa Trial Gratis (hari)
    |--------------------------------------------------------------------------
    | Akun baru otomatis mendapat langganan trial gratis selama N hari.
    | Setelah habis, fitur berbayar (AI Canvas, Turnitin, Plagiarism, download
    | PDF, Workspace, Media, Font) otomatis terkunci lewat hasActiveSubscription().
    */
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 14),
];
