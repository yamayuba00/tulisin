<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Wallet;
use App\Services\CreditPricing;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WalletController extends Controller
{
    /**
     * Saldo kredit pengguna saat ini (tanpa riwayat).
     */
    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);

        return response()->json([
            'balance' => $wallet->balance,
            'on_hold' => $wallet->on_hold,
        ]);
    }

    /**
     * Riwayat transaksi kredit pengguna saat ini (endpoint terpisah dari saldo).
     */
    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginator = $wallet->transactions()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'transactions' => $paginator->getCollection()
                ->map(fn ($t) => [
                    'id' => $t->uuid,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'balance_after' => $t->balance_after,
                    'reason' => $t->reason,
                    'created_at' => $t->created_at?->toIso8601String(),
                ])
                ->values(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * Buat order topup + payment intent QRIS (SumoPod). Koin baru ditambahkan
     * setelah pembayaran dikonfirmasi lewat webhook provider.
     * Konversi: Rp 500 = 1 koin, minimal Rp 25.000. Mendukung kode promo.
     */
    public function topup(Request $request): JsonResponse
    {
        $rate = max(1, CreditPricing::get('topup_rate'));
        $minTopup = max(1000, CreditPricing::get('topup_min'));

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:' . $minTopup],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $amount = (int) $data['amount'];
        $baseCredits = intdiv($amount, $rate);

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
     * Gunakan kredit (potong saldo) untuk suatu fitur. Jumlah biaya dihitung
     * di sisi server dari `reason` + `quantity`/`pages`, agar tarif tidak bisa
     * dimanipulasi lewat request.
     */
    public function spend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:40'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'pages' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $cost = CreditPricing::cost(
            (string) $data['reason'],
            (int) ($data['quantity'] ?? 1),
            (int) ($data['pages'] ?? 0),
        );

        if ($cost === null) {
            return response()->json(['error' => 'Alasan pemakaian tidak dikenal.'], 422);
        }

        $wallet = $this->walletFor($request);

        if ($cost <= 0) {
            return response()->json([
                'message' => 'Tidak ada biaya.',
                'balance' => $wallet->balance,
                'cost' => 0,
            ]);
        }

        try {
            $balance = $wallet->debit($cost, (string) $data['reason']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Koin berhasil digunakan.',
            'balance' => $balance,
            'cost' => $cost,
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
