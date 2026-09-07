<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class LandingController extends Controller
{
    private const AI_ENGINES_KEY = 'ai_engines';

    /**
     * Data publik untuk homepage: harga langganan + mesin AI (tenaga agent).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'monthly_price' => $this->resolveMonthlyPrice(),
            'ai_engines' => $this->resolveAiEngines(),
        ]);
    }

    public static function resolveAiEngines(): array
    {
        $setting = Setting::where('key', self::AI_ENGINES_KEY)->first();
        $engines = $setting ? ($setting->value ?? []) : [];

        return is_array($engines) && $engines !== [] ? array_values($engines) : ['DeepSeek'];
    }

    private function resolveMonthlyPrice(): int
    {
        $setting = Setting::where('key', 'subscription')->first();
        $stored = $setting ? ($setting->value['monthly_price'] ?? null) : null;

        return (int) ($stored ?? 30000);
    }
}
