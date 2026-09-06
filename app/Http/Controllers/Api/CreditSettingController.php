<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditSettingController extends Controller
{
    private const KEY = 'credit_pricing';

    /**
     * Tarif kredit aktif — dibaca oleh seluruh halaman frontend (auth).
     */
    public function pricing(Request $request): JsonResponse
    {
        return response()->json(['pricing' => $this->resolvePricing()]);
    }

    /**
     * Data pengaturan kredit untuk halaman admin.
     */
    public function settings(Request $request): JsonResponse
    {
        return response()->json([
            'pricing' => $this->resolvePricing(),
            'defaults' => config('credits.pricing', []),
        ]);
    }

    /**
     * Simpan tarif kredit baru (super-admin / izin credits.adjust).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $pricing = [];
        foreach (array_keys(config('credits.pricing', [])) as $key) {
            $pricing[$key] = (int) $data[$key];
        }

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $pricing]);

        return response()->json([
            'message' => 'Pengaturan koin berhasil disimpan.',
            'pricing' => $this->resolvePricing(),
        ]);
    }

    private function rules(): array
    {
        return [
            'ai_generate' => ['required', 'integer', 'min:0'],
            'agent_generate' => ['required', 'integer', 'min:0'],
            'ai_plagiarism' => ['required', 'integer', 'min:0'],
            'ai_turnitin' => ['required', 'integer', 'min:0'],
            'template' => ['required', 'integer', 'min:0'],
            'font' => ['required', 'integer', 'min:0'],
            'image_package_size' => ['required', 'integer', 'min:1'],
            'image_package_credits' => ['required', 'integer', 'min:0'],
            'download_base' => ['required', 'integer', 'min:0'],
            'download_per_10_pages' => ['required', 'integer', 'min:0'],
        ];
    }

    private function resolvePricing(): array
    {
        $defaults = config('credits.pricing', []);
        $setting = Setting::where('key', self::KEY)->first();
        $stored = $setting ? $setting->value : [];

        return array_replace($defaults, is_array($stored) ? $stored : []);
    }
}
