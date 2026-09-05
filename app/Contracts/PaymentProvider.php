<?php

namespace App\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProvider
{
    /**
     * Buat transaksi/payment intent di penyedia dan kembalikan data ter-normalisasi.
     *
     * @return array{provider_ref:?string, payment_url:?string, qr_payload:?string, raw:array}
     */
    public function createPayment(Payment $payment, int $total, string $currency): array;

    /**
     * Baca & normalisasi notifikasi webhook dari penyedia.
     *
     * @return array{invoice_number:string, provider_ref:?string, status:string, raw:array}
     */
    public function parseWebhook(Request $request): array;

    /**
     * Verifikasi keaslian request webhook (signature/token). Harus melempar
     * exception bila tidak valid, agar webhook palsu tidak diproses.
     */
    public function verifyWebhook(Request $request): void;
}
