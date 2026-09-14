<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\WebhookVerificationException;
use App\Models\Payment;
use App\Models\Subscription;
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
     * Daftar invoice/faktur milik pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $topupByPayment = TopupOrder::whereIn('payment_id', $payments->pluck('id'))->get()->keyBy('payment_id');
        $subByPayment = Subscription::whereIn('payment_id', $payments->pluck('id'))->get()->keyBy('payment_id');

        return response()->json([
            'invoices' => $payments->map(fn (Payment $p) => $this->invoicePayload($p, $topupByPayment, $subByPayment))->all(),
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

        $topupByPayment = TopupOrder::where('payment_id', $payment->id)->get()->keyBy('payment_id');
        $subByPayment = Subscription::where('payment_id', $payment->id)->get()->keyBy('payment_id');
        $checkout = app(PaymentService::class)->checkoutData($payment);

        return response()->json([
            'invoice' => array_merge($this->invoicePayload($payment, $topupByPayment, $subByPayment), [
                'payment_url' => $checkout['payment_url'],
                'qr_payload' => $checkout['qr_payload'],
            ]),
            'user' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
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

    /**
     * Bentuk data invoice dari payment + relasi topup/langganan.
     */
    private function invoicePayload(Payment $p, $topupByPayment, $subByPayment): array
    {
        $topup = $topupByPayment->get($p->id);
        $sub = $subByPayment->get($p->id);

        $type = $sub ? 'subscription' : ($topup ? 'topup' : 'payment');
        $subtotal = $sub ? (float) $sub->price : (float) ($topup?->amount ?? $p->amount);
        $discount = (int) ($sub?->discount_amount ?? 0);

        return [
            'uuid' => $p->uuid,
            'invoice_number' => $p->invoice_number,
            'type' => $type,
            'item' => $type === 'subscription' ? 'Langganan Bulanan' : 'Isi Saldo Koin',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'fee' => (float) $p->fee,
            'total' => (float) $p->amount,
            'credits' => (int) ($topup?->credits ?? 0),
            'status' => $p->status,
            'method' => $p->method,
            'created_at' => $p->created_at?->toIso8601String(),
            'paid_at' => $p->paid_at?->toIso8601String(),
        ];
    }
}
