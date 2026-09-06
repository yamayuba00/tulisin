<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\DeepSeek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Asisten chat landing (publik). Hanya tanya-jawab seputar Tulisin.
     * Tidak mengubah/mengedit/menghapus data apa pun.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
        ]);

        $reply = app(DeepSeek::class)->chat(
            $this->systemPrompt(),
            $this->buildUserPrompt((string) $data['message'], $data['history'] ?? []),
        );

        if ($reply === null) {
            return response()->json(['error' => 'Gagal menghubungi AI. Coba lagi.'], 502);
        }

        return response()->json(['reply' => $this->cleanReply($reply)]);
    }

    /**
     * Buang penanda markdown (bold/italic/heading/kode) agar tampil sebagai
     * teks biasa di chatbox (UI tidak merender markdown).
     */
    private function cleanReply(string $text): string
    {
        $text = str_replace(['**', '__', '`', '###', '##', '#'], '', $text);

        // Sisa tanda bintang/tanda hubung untuk bullet, dan spasi ganda.
        $text = preg_replace('/^[-*]\s+/m', '• ', $text);
        $text = str_replace('*', '', $text);
        $text = preg_replace('/\s*\n{3,}\s*/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Landasan perilaku asisten: konteks Tulisin + info harga terkini.
     */
    private function systemPrompt(): string
    {
        $subscriptionPrice = $this->subscriptionPrice();
        $pricing = $this->creditPricing();

        $pricingLines = [
            "- Agent AI (buat project): {$pricing['agent_generate']} koin",
            "- Generate AI: {$pricing['ai_generate']} koin",
            "- Plagiarism Optimizer: {$pricing['ai_plagiarism']} koin",
            "- Turnitin Optimizer: {$pricing['ai_turnitin']} koin",
            "- Pakai template: {$pricing['template']} koin",
            "- Unduh dokumen: {$pricing['download_base']} koin + {$pricing['download_per_10_pages']} koin per 10 halaman",
        ];

        $prompt = <<<PROMPT
Anda adalah Asisten Tulisin, asisten virtual untuk Tulisin — platform Agent Document AI untuk penulisan dokumen akademik (skripsi, tesis, disertasi, makalah, jurnal, laporan, proposal, esai).

Tugas Anda:
1. Jawab pertanyaan pengunjung seputar Tulisin: fitur, cara kerja, paket/langganan, harga, topup koin, dan penggunaan aplikasi.
2. Jawab dengan bahasa yang digunakan pengguna (default bahasa Indonesia), jelas, ringkas, dan ramah.
3. Tetap dalam konteks Tulisin (akademik & penulisan dokumen). Jika pertanyaan di luar konteks, sampaikan dengan sopan bahwa Anda hanya bisa membantu seputar Tulisin, lalu arahkan kembali ke topik Tulisin.
4. Jika berguna, akhiri jawaban dengan 1-3 saran pertanyaan lanjutan yang relevan agar pengguna bisa langsung menanyakannya.

Informasi terkini (wajib dipakai bila relevan):
- Harga langganan bulanan: Rp {$subscriptionPrice} / 30 hari.
- Topup koin: Rp 500 = 1 koin, minimal topup Rp 25.000.
- Koin dipakai untuk fitur AI & premium, dengan tarif:
PROMPT;

        foreach ($pricingLines as $line) {
            $prompt .= "\n{$line}";
        }

        $prompt .= <<<PROMPT

Batasan penting:
- Anda TIDAK mengubah, mengedit, atau menghapus data/dokumen apa pun. Anda hanya menjawab pertanyaan.
- Jangan gunakan markdown untuk memformat teks (jangan pakai ** untuk tebal, * untuk miring, # untuk judul, atau ` untuk kode). Tulis jawaban sebagai teks biasa.
- Jangan mengarang harga atau fitur yang tidak tercantum. Jika ragu, sarankan pengunjung membuka halaman Topup atau menghubungi tim.
- Jangan membahas topik di luar Tulisin.
PROMPT;

        return $prompt;
    }

    /**
     * Susun pesan user: sertakan riwayat percakapan bila ada.
     */
    private function buildUserPrompt(string $message, array $history): string
    {
        if ($history === []) {
            return $message;
        }

        $lines = [];
        foreach ($history as $turn) {
            if (! is_array($turn)) {
                continue;
            }
            $role = ($turn['role'] ?? '') === 'assistant' ? 'Asisten' : 'Pengunjung';
            $content = $turn['content'] ?? '';
            if (is_string($content) && $content !== '') {
                $lines[] = "{$role}: {$content}";
            }
        }

        if ($lines === []) {
            return $message;
        }

        return "Riwayat percakapan:\n".implode("\n", $lines)."\n\nPertanyaan terbaru:\n{$message}";
    }

    private function subscriptionPrice(): int
    {
        $setting = Setting::where('key', 'subscription')->first();

        return (int) ($setting->value['monthly_price'] ?? 30000);
    }

    private function creditPricing(): array
    {
        $defaults = config('credits.pricing', []);
        $setting = Setting::where('key', 'credit_pricing')->first();
        $stored = $setting ? $setting->value : [];

        return array_replace($defaults, is_array($stored) ? $stored : []);
    }
}
