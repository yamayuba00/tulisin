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

        $payload = [
            'model' => (string) config('services.deepseek.model', 'deepseek-v4-flash'),
            'messages' => $messages,
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
}
