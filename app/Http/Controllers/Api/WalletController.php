<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Wallet;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WalletController extends Controller
{
    /**
     * Saldo + riwayat transaksi kredit pengguna saat ini.
     */
    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);

        return response()->json([
            'balance' => $wallet->balance,
            'on_hold' => $wallet->on_hold,
            'transactions' => $wallet->transactions()
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->uuid,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'balance_after' => $t->balance_after,
                    'reason' => $t->reason,
                    'created_at' => $t->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Buat order topup + payment intent QRIS (SumoPod). Koin baru ditambahkan
     * setelah pembayaran dikonfirmasi lewat webhook provider.
     * Konversi: Rp 500 = 1 koin, minimal Rp 25.000. Mendukung kode promo.
     */
    public function topup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:25000'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $amount = (int) $data['amount'];
        $baseCredits = intdiv($amount, 500);

        $coupon = null;
        $couponCode = null;
        $payable = $amount;
        $bonusCredits = 0;

        if (! empty($data['coupon'])) {
            $coupon = Coupon::where('code', strtoupper(trim((string) $data['coupon'])))->first();

            if (! $coupon) {
                return response()->json(['error' => 'Kode promo tidak ditemukan.'], 422);
            }

            if (! $coupon->isUsable()) {
                return response()->json(['error' => 'Kode promo tidak valid atau sudah tidak bisa dipakai.'], 422);
            }

            $effect = $coupon->apply($amount, $baseCredits);
            $payable = $effect['payable'];
            $bonusCredits = $effect['bonus_credits'];
            $couponCode = $coupon->code;
        }

        $totalCredits = $baseCredits + $bonusCredits;

        try {
            $payment = app(PaymentService::class)->createTopupPayment(
                $request->user(),
                $payable,
                $totalCredits,
                $couponCode,
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $checkout = app(PaymentService::class)->checkoutData($payment);

        return response()->json([
            'message' => 'Silakan selesaikan pembayaran QRIS.',
            'payment' => [
                'uuid' => $payment->uuid,
                'invoice_number' => $payment->invoice_number,
                'base_amount' => $payable,
                'fee' => $payment->fee,
                'amount' => $payment->amount,
                'credits' => $totalCredits,
                'status' => $payment->status,
                'payment_url' => $checkout['payment_url'],
                'qr_payload' => $checkout['qr_payload'],
            ],
        ], 201);
    }

    /**
     * Gunakan kredit (potong saldo) untuk suatu fitur.
     */
    public function spend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'credits' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:40'],
        ]);

        $wallet = $this->walletFor($request);

        try {
            $balance = $wallet->debit((int) $data['credits'], (string) $data['reason']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Koin berhasil digunakan.',
            'balance' => $balance,
        ]);
    }

    /**
     * Ambil (atau buat) wallet milik pengguna yang sedang login.
     */
    private function walletFor(Request $request): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $request->user()->id]);
    }
}
