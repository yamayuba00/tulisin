<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Sumber tunggal tarif kredit. Nilai aktual disimpan di tabel `settings`
 * (key: credit_pricing) dan digabung dengan default dari config/credits.php.
 */
class CreditPricing
{
    public static function all(): array
    {
        $defaults = config('credits.pricing', []);
        $setting = Setting::where('key', 'credit_pricing')->first();
        $stored = $setting ? $setting->value : [];

        return array_replace($defaults, is_array($stored) ? $stored : []);
    }

    public static function get(string $key): int
    {
        return (int) (self::all()[$key] ?? 0);
    }

    /**
     * Hitung biaya koin untuk sebuah alasan pemakaian (reason), dihitung di
     * sisi server agar tidak bisa dimanipulasi dari frontend.
     */
    public static function cost(string $reason, int $quantity = 1, int $pages = 0): ?int
    {
        $p = self::all();
        $quantity = max(1, $quantity);

        switch ($reason) {
            case 'ai_generate':
                return (int) $p['ai_generate'];
            case 'agent_generate':
                return (int) $p['agent_generate'];
            case 'plagiarism_check':
            case 'plagiarism_paraphrase':
                return (int) $p['ai_plagiarism'];
            case 'turnitin_optimize':
                return (int) $p['ai_turnitin'];
            case 'template_use':
                return (int) $p['template'];
            case 'font_upload':
                return (int) $p['font'] * $quantity;
            case 'image_upload':
                return self::imageCost($quantity, $p);
            case 'download':
                return self::downloadCost($pages, $p);
            default:
                return null;
        }
    }

    private static function imageCost(int $quantity, array $p): int
    {
        $size = max(1, (int) ($p['image_package_size'] ?? 1));
        $credits = (int) ($p['image_package_credits'] ?? 0);

        if ($credits <= 0) {
            return 0;
        }

        return (int) ceil($quantity * $credits / $size);
    }

    private static function downloadCost(int $pages, array $p): int
    {
        $base = (int) ($p['download_base'] ?? 0);
        $per10 = (int) ($p['download_per_10_pages'] ?? 0);
        $steps = $pages > 0 ? (int) floor(($pages - 1) / 10) : 0;

        return $base + $per10 * $steps;
    }
}
