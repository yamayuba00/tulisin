<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    private const KEY = 'promo_modal';

    private const DEFAULTS = [
        'enabled' => false,
        'badge' => 'Promo Hari Ini',
        'highlight' => '',
        'subtitle' => '',
        'title' => '',
        'description' => '',
        'cta_label' => 'Klaim Promo',
        'cta_link' => '/apps/u/topup',
        'cta_secondary_label' => 'Nanti saja',
    ];

    /**
     * Banner promo aktif untuk popup harian (publik, tanpa login).
     */
    public function show(Request $request): JsonResponse
    {
        $promo = $this->resolve();

        if (empty($promo['enabled']) || (empty($promo['title']) && empty($promo['highlight']))) {
            return response()->json(['promo' => null]);
        }

        return response()->json(['promo' => $promo]);
    }

    /**
     * Data pengaturan banner promo untuk halaman admin.
     */
    public function settings(Request $request): JsonResponse
    {
        return response()->json([
            'promo' => $this->resolve(),
            'defaults' => self::DEFAULTS,
        ]);
    }

    /**
     * Simpan pengaturan banner promo.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'badge' => ['nullable', 'string', 'max:60'],
            'highlight' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_link' => ['nullable', 'string', 'max:191'],
            'cta_secondary_label' => ['nullable', 'string', 'max:60'],
        ]);

        $promo = array_replace(self::DEFAULTS, $data);

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $promo]);

        return response()->json([
            'message' => 'Banner promo berhasil disimpan.',
            'promo' => $this->resolve(),
        ]);
    }

    private function resolve(): array
    {
        $setting = Setting::where('key', self::KEY)->first();
        $stored = $setting ? $setting->value : [];

        return array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }
}
