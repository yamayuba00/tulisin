<?php

namespace App\Jobs;

use App\Services\PdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Render PDF di belakang layar (queue) supaya request HTTP tidak menahan worker
 * PHP-FPM dan lonjakan trafik tidak membuat proses Chrome menumpuk sekaligus.
 */
class ExportPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Antrian khusus agar render PDF (lama) tidak memblokir notifikasi cepat. */
    public $queue = 'exports';

    /** Batas waktu render agar tidak menggantung selamanya. */
    public int $timeout = 600;

    /** Cukup satu kali; kegagalan dilaporkan lewat status cache. */
    public int $tries = 1;

    public function __construct(
        public string $token,
        public int $userId,
        public array $pages,
        public string $head,
        public ?string $projectId,
        public string $format,
    ) {
    }

    public function handle(PdfRenderer $renderer): void
    {
        $this->setStatus('processing');

        $outPath = storage_path('app/export').DIRECTORY_SEPARATOR.$this->token.'.pdf';

        try {
            $renderer->render($outPath, $this->pages, $this->head);

            record_audit_user($this->userId, 'export_pdf', [
                'project' => $this->projectId,
                'format' => $this->format,
                'pages' => count($this->pages),
            ]);

            $this->setStatus('done');
        } catch (\Throwable $e) {
            $this->setStatus('failed', $e->getMessage());
            throw $e;
        }
    }

    private function setStatus(string $status, ?string $error = null): void
    {
        Cache::put('export:'.$this->token, [
            'status' => $status,
            'user_id' => $this->userId,
            'error' => $error,
        ], now()->addMinutes(60));
    }
}
