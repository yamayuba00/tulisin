<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeek
{
    /** Percobaan maksimum (1 percobaan awal + 2 ulang) untuk error sementara. */
    private const MAX_ATTEMPTS = 3;

    /** Jeda antar percobaan (milidetik). */
    private const RETRY_DELAY_MS = 1200;

    /**
     * Kirim percakapan ke DeepSeek (OpenAI-compatible) dan kembalikan isi balasan.
     *
     * Error sementara (jaringan, 429, 5xx) diulang otomatis dengan backoff agar
     * user tidak melihat kegagalan hanya karena gangguan sesaat.
     *
     * @param  bool  $json  aktifkan mode JSON (response_format json_object).
     * @param  float  $temperature  kreativitas balasan (0 = deterministik, 1 = bebas).
     * @param  array  $history  riwayat percakapan sebelumnya [{role, content}, ...].
     * @return string|null  isi balasan, atau null bila gagal.
     */
    public function chat(string $system, string $user, bool $json = false, float $temperature = 0.7, array $history = []): ?string
    {
        $payload = [
            'model' => (string) config('services.deepseek.model', 'deepseek-v4-flash'),
            'messages' => $this->messages($system, $user, $history),
            'temperature' => $json ? 0 : $temperature,
            // Batasi panjang balasan agar tidak menggantung lama (jaga waktu, cegah timeout).
            'max_tokens' => $json ? 2000 : 4096,
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $url = rtrim((string) config('services.deepseek.base_url'), '/').'/chat/completions';
        $token = (string) config('services.deepseek.api_key');

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->connectTimeout(15)
                    ->withToken($token)
                    ->post($url, $payload);
            } catch (\Throwable $e) {
                Log::warning('DeepSeek request gagal (jaringan)', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                $this->waitBeforeRetry($attempt);

                continue;
            }

            if ($response->successful()) {
                $content = (string) $response->json('choices.0.message.content', '');

                // Balasan kosong dianggap gagal sementara; coba lagi.
                if (trim($content) !== '') {
                    return $content;
                }

                Log::warning('DeepSeek balasan kosong', ['attempt' => $attempt]);
                $this->waitBeforeRetry($attempt);

                continue;
            }

            // Error yang tidak akan berubah walau diulang: jangan buang waktu.
            if (! $this->isRetryableStatus($response->status())) {
                Log::warning('DeepSeek gagal permanen', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            Log::warning('DeepSeek gagal sementara', [
                'status' => $response->status(),
                'attempt' => $attempt,
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            // Hormati Retry-After bila provider mengirimkannya.
            $retryAfter = (int) ($response->header('Retry-After') ?: 0);
            if ($retryAfter > 0) {
                usleep(min($retryAfter, 5) * 1_000_000);
            } else {
                $this->waitBeforeRetry($attempt);
            }
        }

        Log::error('DeepSeek gagal setelah semua percobaan', ['attempts' => self::MAX_ATTEMPTS]);

        return null;
    }

    /** Status HTTP yang layak diulang (gangguan sementara provider). */
    private function isRetryableStatus(int $status): bool
    {
        return in_array($status, [408, 409, 425, 429, 500, 502, 503, 504, 522, 524], true);
    }

    /** Backoff bertingkat sebelum percobaan berikutnya. */
    private function waitBeforeRetry(int $attempt): void
    {
        if ($attempt < self::MAX_ATTEMPTS) {
            usleep(self::RETRY_DELAY_MS * $attempt * 1000);
        }
    }

    /**
     * Stream percakapan ke DeepSeek (SSE) dan panggil $onDelta untuk tiap potongan
     * teks yang diterima. Mengembalikan teks lengkap, atau null bila gagal.
     *
     * @param  callable(string):void  $onDelta
     */
    public function stream(string $system, string $user, bool $json, float $temperature, array $history, callable $onDelta): ?string
    {
        $payload = [
            'model' => (string) config('services.deepseek.model', 'deepseek-v4-flash'),
            'messages' => $this->messages($system, $user, $history),
            'temperature' => $json ? 0 : $temperature,
            'stream' => true,
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken((string) config('services.deepseek.api_key'))
            ->send(
                'POST',
                rtrim((string) config('services.deepseek.base_url'), '/').'/chat/completions',
                ['json' => $payload, 'stream' => true, 'timeout' => 0, 'connect_timeout' => 30],
            );

        if (! $response->successful()) {
            return null;
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $full = '';

        while (! $body->eof()) {
            $chunk = $body->read(4096);
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));
                if ($data === '[DONE]') {
                    return $full;
                }

                $decoded = json_decode($data, true);
                $delta = is_array($decoded) ? (string) ($decoded['choices'][0]['delta']['content'] ?? '') : '';
                if ($delta !== '') {
                    $full .= $delta;
                    $onDelta($delta);
                }
            }
        }

        return $full;
    }

    /**
     * Susun daftar pesan (system + history + user) untuk dikirim ke DeepSeek.
     */
    private function messages(string $system, string $user, array $history): array
    {
        $messages = [['role' => 'system', 'content' => $system]];

        foreach ($history as $turn) {
            $role = (string) ($turn['role'] ?? '');
            $content = (string) ($turn['content'] ?? '');
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $user];

        return $messages;
    }
}
