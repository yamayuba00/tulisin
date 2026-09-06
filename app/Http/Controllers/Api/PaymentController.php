<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TopupOrder;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    /**
     * Info biaya & konfigurasi pembayaran (untuk pratinjau di halaman topup).
     */
    public function meta(Request $request): JsonResponse
    {
        return response()->json([
            'fee_fixed' => (int) config('payments.fee_fixed', 2000),
            'fee_percent' => (float) config('payments.fee_percent', 0),
            'currency' => (string) config('payments.currency', 'IDR'),
            'expires_in_hours' => (int) config('payments.expires_in_hours', 24),
        ]);
    }

    /**
     * Cek status pembayaran milik pengguna yang sedang login.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return response()->json(['error' => 'Pembayaran tidak ditemukan.'], 404);
        }

        $order = TopupOrder::where('payment_id', $payment->id)->first();
        $checkout = app(PaymentService::class)->checkoutData($payment);

        return response()->json([
            'payment' => [
                'uuid' => $payment->uuid,
                'invoice_number' => $payment->invoice_number,
                'amount' => $payment->amount,
                'fee' => $payment->fee,
                'status' => $payment->status,
                'credits' => $order?->credits ?? 0,
                'payment_url' => $checkout['payment_url'],
                'qr_payload' => $checkout['qr_payload'],
            ],
        ]);
    }

    /**
     * Webhook provider (dipanggil SumoPod tanpa auth).
     */
    public function webhook(Request $request, string $provider): JsonResponse
    {
        try {
            $payment = app(PaymentService::class)->handleWebhook($provider, $request);
        } catch (WebhookVerificationException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($payment === null) {
            return response()->json(['status' => 'ok', 'test' => true]);
        }

        return response()->json([
            'status' => $payment->status,
            'invoice_number' => $payment->invoice_number,
        ]);
    }
}
