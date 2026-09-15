<?php

namespace App\Jobs;

use App\Services\DeepSeek;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Jalankan permintaan AI (DeepSeek) di belakang layar supaya request HTTP tidak
 * menahan worker PHP-FPM. Hasil & status disimpan di Cache dengan key
 * `ai:generate:{token}`, lalu di-poll oleh frontend.
 */
class GenerateAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Batas waktu pemrosesan agar tidak menggantung selamanya. */
    public int $timeout = 120;

    /** Cukup sekali; kegagalan dilaporkan lewat status cache. */
    public int $tries = 1;

    public function __construct(
        public string $token,
        public int $userId,
        public string $system,
        public string $user,
        public bool $json,
        public float $temperature,
        public array $history = [],
    ) {
        // Antrian khusus agar job AI tidak bercampur/memblokir job cepat lain.
        $this->queue = 'ai';
    }

    public function handle(DeepSeek $deepSeek): void
    {
        $this->setStatus('processing');

        try {
            $reply = $deepSeek->chat(
                $this->system,
                $this->user,
                $this->json,
                $this->temperature,
                $this->history,
            );

            if ($reply === null) {
                $this->setStatus('failed', 'Gagal menghubungi AI. Coba lagi.');

                return;
            }

            $this->setStatus('done', null, $reply);
        } catch (\Throwable $e) {
            Log::error('GenerateAiJob gagal', [
                'token' => $this->token,
                'error' => $e->getMessage(),
            ]);

            $this->setStatus('failed', 'Gagal menghubungi AI. Coba lagi.');
        }
    }

    private function setStatus(string $status, ?string $error = null, ?string $reply = null): void
    {
        Cache::put('ai:generate:'.$this->token, [
            'status' => $status,
            'user_id' => $this->userId,
            'reply' => $reply,
            'error' => $error,
        ], now()->addMinutes(10));
    }
}
