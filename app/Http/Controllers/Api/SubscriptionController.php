<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    private const KEY = 'subscription';

    /**
     * Status langganan + harga bulanan untuk pengguna aktif.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json([
            'monthly_price' => $this->resolvePrice(),
            'active' => $subscription?->isActive() ?? false,
            'subscription' => $subscription ? [
                'id' => $subscription->uuid,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
                'price' => (int) $subscription->price,
            ] : null,
        ]);
    }

    /**
     * Aktifkan / perpanjang langganan (simulasi pembayaran tanpa gateway).
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        $price = $this->resolvePrice();

        $active = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest()
            ->first();

        if ($active) {
            $active->update([
                'ends_at' => $active->ends_at->addDays(Subscription::PERIOD_DAYS),
                'price' => (int) $active->price + $price,
            ]);
            $subscription = $active;
        } else {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays(Subscription::PERIOD_DAYS),
                'price' => $price,
                'payment_method' => 'simulation',
            ]);
        }

        return response()->json([
            'message' => 'Langganan berhasil diaktifkan.',
            'active' => true,
            'ends_at' => $subscription->ends_at?->toISOString(),
        ], 201);
    }

    /**
     * Data pengaturan langganan untuk admin.
     */
    public function settings(Request $request): JsonResponse
    {
        return response()->json([
            'monthly_price' => $this->resolvePrice(),
            'default_price' => 30000,
        ]);
    }

    /**
     * Simpan harga langganan bulanan (admin).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'monthly_price' => ['required', 'integer', 'min:1000'],
        ]);

        Setting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => ['monthly_price' => (int) $data['monthly_price']]],
        );

        return response()->json([
            'message' => 'Harga langganan berhasil disimpan.',
            'monthly_price' => $this->resolvePrice(),
        ]);
    }

    private function resolvePrice(): int
    {
        $setting = Setting::where('key', self::KEY)->first();
        $stored = $setting ? ($setting->value['monthly_price'] ?? null) : null;

        return (int) ($stored ?? 30000);
    }
}
