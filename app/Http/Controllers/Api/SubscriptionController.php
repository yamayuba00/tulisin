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
     * Aktifkan / perpanjang langganan via pembayaran QRIS (SumoPod).
     * Langganan baru diaktifkan setelah webhook konfirmasi pembayaran.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        $price = $this->resolvePrice();

        try {
            $payment = app(PaymentService::class)->createSubscriptionPayment($user, $price);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $checkout = app(PaymentService::class)->checkoutData($payment);

        return response()->json([
            'message' => 'Silakan selesaikan pembayaran QRIS.',
            'payment' => [
                'uuid' => $payment->uuid,
                'invoice_number' => $payment->invoice_number,
                'amount' => $payment->amount,
                'fee' => $payment->fee,
                'status' => $payment->status,
                'payment_url' => $checkout['payment_url'],
                'qr_payload' => $checkout['qr_payload'],
            ],
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
