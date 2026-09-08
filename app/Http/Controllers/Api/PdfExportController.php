<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExportPdfJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfExportController extends Controller
{
    /**
     * Antrikan pembuatan PDF (async). Request segera selesai, render dilakukan
     * oleh queue worker sehingga tidak menahan worker PHP-FPM dan tidak
     * menumpuk proses Chrome saat trafik tinggi.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->hasActiveSubscription()) {
            return response()->json(['error' => 'Download PDF memerlukan langganan aktif.'], 402);
        }

        $head = (string) $request->input('head', '');
        $pages = $request->input('pages');
        $html = (string) $request->input('html', '');

        if (is_array($pages) && $pages !== []) {
            $pages = array_values(array_map('strval', $pages));
        } elseif ($html !== '') {
            // Mode lama: satu dokumen utuh diperlakukan sebagai satu "halaman".
            $pages = [$html];
            $head = '';
        } else {
            return response()->json(['error' => 'Konten dokumen kosong.'], 422);
        }

        $token = (string) Str::uuid();

        Cache::put('export:'.$token, [
            'status' => 'queued',
            'user_id' => $request->user()->id,
            'error' => null,
        ], now()->addMinutes(60));

        ExportPdfJob::dispatch(
            $token,
            $request->user()->id,
            $pages,
            $head,
            $request->input('project'),
            (string) $request->input('format', 'pdf'),
        );

        return response()->json(['token' => $token, 'status' => 'queued'], 202);
    }

    /**
     * Cek status render PDF (dipolling frontend).
     */
    public function status(Request $request, string $token): JsonResponse
    {
        $state = $this->state($request, $token);

        if ($state === null) {
            return response()->json(['status' => 'failed', 'error' => 'Ekspor kedaluwarsa atau tidak ditemukan.'], 404);
        }

        $result = $state;
        if (($state['status'] ?? null) === 'done') {
            $result['downloadUrl'] = '/api/export/pdf/'.$token.'/download';
        }

        return response()->json($result);
    }

    /**
     * Unduh hasil PDF setelah selesai (file otomatis terhapus setelah dikirim).
     */
    public function download(Request $request, string $token): BinaryFileResponse|JsonResponse
    {
        $state = $this->state($request, $token);

        if ($state === null || ($state['status'] ?? null) !== 'done') {
            return response()->json(['error' => 'File PDF belum siap atau sudah dihapus.'], 404);
        }

        $file = storage_path('app/export').DIRECTORY_SEPARATOR.$token.'.pdf';
        if (! is_file($file)) {
            return response()->json(['error' => 'File PDF belum siap atau sudah dihapus.'], 404);
        }

        Cache::forget('export:'.$token);

        return response()
            ->download($file, 'dokumen.pdf', ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Ambil status cache milik user yang sedang login.
     */
    private function state(Request $request, string $token): ?array
    {
        $state = Cache::get('export:'.$token);

        if (! is_array($state)) {
            return null;
        }

        if ((int) ($state['user_id'] ?? 0) !== (int) $request->user()->id) {
            return null;
        }

        return $state;
    }
}
