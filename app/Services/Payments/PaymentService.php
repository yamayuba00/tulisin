<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\TopupOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    /**
     * Resolve provider aktif berdasarkan konfigurasi (titik swap cepat).
     */
    public function provider(?string $name = null): PaymentProvider
    {
        $name ??= (string) config('payments.provider');

        return match ($name) {
            'sumopod' => app(SumoPodProvider::class),
            default => throw new RuntimeException("Provider pembayaran '{$name}' tidak dikenal."),
        };
    }

    /**
     * Biaya tambahan (tetap + persen) di atas nominal koin.
     */
    public function feeFor(int $amount): int
    {
        $fixed = (int) config('payments.fee_fixed', 2000);
        $percent = (float) config('payments.fee_percent', 0);

        return $fixed + (int) round($amount * ($percent / 100));
    }

    /**
     * Buat order topup + payment intent, lalu minta QRIS ke provider.
     */
    public function createTopupPayment(User $user, int $baseAmount, int $totalCredits, ?string $couponCode = null): Payment
    {
        $fee = $this->feeFor($baseAmount);
        $total = $baseAmount + $fee;
        $invoice = $this->generateInvoiceNumber();

        return DB::transaction(function () use ($user, $baseAmount, $fee, $total, $totalCredits, $invoice, $couponCode) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'invoice_number' => $invoice,
                'amount' => $total,
                'fee' => $fee,
                'method' => 'QRIS',
                'provider' => config('payments.provider'),
                'status' => 'pending',
            ]);

            TopupOrder::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'credits' => $totalCredits,
                'amount' => $baseAmount,
                'coupon_code' => $couponCode,
                'status' => 'pending',
            ]);

            $result = $this->provider()->createPayment(
                $payment,
                $total,
                (string) config('payments.currency', 'IDR'),
            );

            $payment->update([
                'provider_ref' => $result['provider_ref'] ?? null,
                'payment_url' => $result['payment_url'] ?? null,
                'payload' => $result,
                'expires_at' => now()->addHours((int) config('payments.expires_in_hours', 24)),
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Proses webhook provider: tandai lunas, tambah koin, kirim notifikasi admin.
     */
    public function handleWebhook(string $providerName, Request $request): Payment
    {
        $provider = $this->provider($providerName);
        $provider->verifyWebhook($request);

        $data = $provider->parseWebhook($request);

        if (empty($data['invoice_number'])) {
            throw new RuntimeException('Webhook tidak menyertakan order_id / invoice_number.');
        }

        $payment = Payment::where('invoice_number', $data['invoice_number'])->first();
        if (! $payment) {
            throw new RuntimeException('Pembayaran tidak ditemukan: ' . $data['invoice_number']);
        }

        if ($data['status'] === 'paid' && ! $payment->isPaid()) {
            $this->markPaid($payment, $data['provider_ref']);
        } elseif (in_array($data['status'], ['failed', 'expired'], true)) {
            $payment->update(['status' => $data['status']]);
        }

        return $payment->fresh();
    }

    /**
     * @return array{payment_url:?string, qr_payload:?string}
     */
    public function checkoutData(Payment $payment): array
    {
        return [
            'payment_url' => $payment->payment_url,
            'qr_payload' => $payment->payload['qr_payload'] ?? null,
        ];
    }

    private function markPaid(Payment $payment, ?string $providerRef): void
    {
        DB::transaction(function () use ($payment, $providerRef) {
            $payment->update([
                'status' => 'paid',
                'provider_ref' => $providerRef ?? $payment->provider_ref,
                'paid_at' => now(),
            ]);

            $order = TopupOrder::where('payment_id', $payment->id)->first();
            if ($order && $order->status !== 'completed') {
                $wallet = Wallet::firstOrCreate(['user_id' => $payment->user_id]);
                $wallet->credit($order->credits, 'topup', 'topup_order', $order->id);
                $order->update(['status' => 'completed']);

                $this->redeemCoupon($order);
            }
        });

        $this->notifyAdmin($payment);
    }

    private function redeemCoupon(TopupOrder $order): void
    {
        if (! $order->coupon_code) {
            return;
        }

        $coupon = Coupon::where('code', $order->coupon_code)->first();
        if (! $coupon) {
            return;
        }

        $coupon->increment('used_count');

        DB::table('coupon_usages')->insert([
            'uuid' => (string) Str::uuid(),
            'coupon_id' => $coupon->id,
            'user_id' => $order->user_id,
            'reference_type' => 'topup_order',
            'reference_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notifyAdmin(Payment $payment): void
    {
        $settings = \App\Models\Setting::where('key', 'notifications')->first();
        $value = $settings ? ($settings->value ?? []) : [];
        $adminEmail = $value['admin_email'] ?? null;
        $enabled = (bool) ($value['notify_payment'] ?? true);

        if (! $adminEmail || ! $enabled) {
            return;
        }

        \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
            ->notify(new PaymentReceivedNotification($payment));
    }

    private function generateInvoiceNumber(): string
    {
        $date = now()->format('dmy');
        $count = Payment::whereDate('created_at', today())->count() + 1;
        $prefix = 'T' . $date . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

        do {
            $unique = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $invoice = $prefix . $unique;
        } while (Payment::where('invoice_number', $invoice)->exists());

        return $invoice;
    }
}
