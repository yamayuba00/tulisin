<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SumoPodProvider implements PaymentProvider
{
    /**
     * Buat payment intent QRIS di SumoPod.
     */
    public function createPayment(Payment $payment, int $total, string $currency): array
    {
        $config = (array) config('payments.providers.sumopod', []);
        $sandbox = (bool) ($config['sandbox'] ?? true);
        $baseUrl = rtrim((string) ($sandbox ? ($config['sandbox_base_url'] ?? '') : ($config['live_base_url'] ?? '')), '/');
        $apiKey = $sandbox ? ($config['sandbox_api_key'] ?? null) : ($config['live_api_key'] ?? null);

        if ($baseUrl === '' || ! $apiKey) {
            throw new RuntimeException('Konfigurasi SumoPod belum lengkap.');
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $invoice = $payment->invoice_number;

        $successUrl = config('payments.success_return_url')
            ?: $appUrl . '/apps/u/topup?status=success&order=' . $invoice;
        $cancelUrl = config('payments.cancel_return_url')
            ?: $appUrl . '/apps/u/topup?status=cancel&order=' . $invoice;

        $response = Http::asJson()
            ->acceptJson()
            ->withHeaders(['X-Api-Key' => $apiKey])
            ->post($baseUrl . '/api/v1/payments', [
                'order_id' => $invoice,
                'amount' => $total,
                'currency' => $currency,
                'expires_in_hours' => (int) config('payments.expires_in_hours', 24),
                'success_return_url' => $successUrl,
                'cancel_return_url' => $cancelUrl,
                'payment_method_type_code' => 'QRIS',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal membuat pembayaran di SumoPod: ' . $response->body());
        }

        $data = $response->json() ?? [];

        return [
            'provider_ref' => $data['id'] ?? $data['payment_id'] ?? $data['order_id'] ?? null,
            'payment_url' => $data['payment_url'] ?? $data['checkout_url'] ?? $data['pay_url'] ?? $data['url'] ?? null,
            'qr_payload' => $data['qr_code'] ?? $data['qr_string'] ?? $data['qr_content'] ?? $data['qr_payload'] ?? null,
            'raw' => $data,
        ];
    }

    /**
     * Baca notifikasi webhook SumoPod.
     *
     * Skema body SumoPod bisa berbeda saat live; sesuaikan pemetaan field di bawah.
     */
    public function parseWebhook(Request $request): array
    {
        $data = $request->all();

        $rawStatus = strtolower((string) ($data['status'] ?? $data['payment_status'] ?? ''));

        $status = match (true) {
            in_array($rawStatus, ['paid', 'success', 'completed', 'settled'], true) => 'paid',
            in_array($rawStatus, ['failed', 'failure', 'cancelled', 'canceled'], true) => 'failed',
            $rawStatus === 'expired' => 'expired',
            default => 'pending',
        };

        return [
            'invoice_number' => (string) ($data['order_id'] ?? $data['invoice_number'] ?? $data['reference'] ?? ''),
            'provider_ref' => $data['payment_id'] ?? $data['id'] ?? null,
            'status' => $status,
            'raw' => $data,
        ];
    }

    /**
     * Verifikasi webhook SumoPod: token statis ATAU signature Svix-style HMAC.
     */
    public function verifyWebhook(Request $request): void
    {
        $config = (array) config('payments.providers.sumopod', []);
        $secret = (string) ($config['webhook_secret'] ?? '');
        $token = (string) ($config['webhook_token'] ?? '');

        // Metode 1: token statis (Authorization: Bearer <token>).
        if ($token !== '') {
            $provided = $request->bearerToken();
            if ($provided === null || ! hash_equals($token, $provided)) {
                throw new RuntimeException('Token webhook tidak valid.');
            }

            return;
        }

        // Metode 2: signature Svix-style (header svix-id/timestamp/signature).
        if ($secret === '') {
            throw new RuntimeException('Webhook secret belum dikonfigurasi.');
        }

        $id = (string) $request->header('svix-id', '');
        $timestamp = (string) $request->header('svix-timestamp', '');
        $signatures = (string) $request->header('svix-signature', '');

        if ($id === '' || $timestamp === '' || $signatures === '') {
            throw new RuntimeException('Header signature webhook tidak lengkap.');
        }

        $secretKey = $this->decodeWebhookSecret($secret);
        $signedContent = $id . '.' . $timestamp . '.' . $request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $secretKey, true));

        foreach (explode(' ', $signatures) as $part) {
            // Format Svix: "v1,<base64>" (bisa lebih dari satu, dipisah spasi).
            $sig = str_contains($part, ',') ? substr($part, strpos($part, ',') + 1) : $part;
            if ($sig !== '' && hash_equals($expected, $sig)) {
                return;
            }
        }

        throw new RuntimeException('Signature webhook tidak valid.');
    }

    /**
     * Secret Svix berawalan "whsec_" dan isinya base64.
     */
    private function decodeWebhookSecret(string $secret): string
    {
        if (str_starts_with($secret, 'whsec_')) {
            $secret = substr($secret, 6);
        }

        $decoded = base64_decode($secret, true);

        return $decoded === false ? $secret : $decoded;
    }
}
