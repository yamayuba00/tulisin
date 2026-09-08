<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DeepSeek
{
    /**
     * Kirim percakapan ke DeepSeek (OpenAI-compatible) dan kembalikan isi balasan.
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
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(90)
            ->withToken((string) config('services.deepseek.api_key'))
            ->post(rtrim((string) config('services.deepseek.base_url'), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            return null;
        }

        return (string) $response->json('choices.0.message.content', '');
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
