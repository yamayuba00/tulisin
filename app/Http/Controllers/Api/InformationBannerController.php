<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformationBannerController extends Controller
{
    private const KEY = 'information_banner';

    private const DEFAULTS = [
        'enabled' => false,
        'text' => '',
        'link_text' => '',
        'link_url' => '',
    ];

    /**
     * Banner informasi aktif untuk ditampilkan di atas header homepage (publik).
     */
    public function show(Request $request): JsonResponse
    {
        $banner = $this->resolve();

        if (empty($banner['enabled']) || trim((string) $banner['text']) === '') {
            return response()->json(['banner' => null]);
        }

        return response()->json(['banner' => $banner]);
    }

    /**
     * Data pengaturan banner informasi untuk halaman admin.
     */
    public function settings(Request $request): JsonResponse
    {
        return response()->json([
            'banner' => $this->resolve(),
            'defaults' => self::DEFAULTS,
        ]);
    }

    /**
     * Simpan pengaturan banner informasi.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'text' => ['nullable', 'string', 'max:500'],
            'link_text' => ['nullable', 'string', 'max:60'],
            'link_url' => ['nullable', 'string', 'max:191'],
        ]);

        $banner = array_replace(self::DEFAULTS, $data);

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $banner]);

        return response()->json([
            'message' => 'Banner informasi berhasil disimpan.',
            'banner' => $this->resolve(),
        ]);
    }

    private function resolve(): array
    {
        $setting = Setting::where('key', self::KEY)->first();
        $stored = $setting ? $setting->value : [];

        return array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }
}
