<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

class PdfExportController extends Controller
{
    /**
     * Jumlah halaman per batch render Chrome.
     * Makin kecil makin ringan tiap proses, makin besar makin cepat totalnya.
     * 5 = titik tengah yang aman.
     */
    private const BATCH_SIZE = 5;

    /** Pesan error terakhir dari Chrome (untuk diagnostik saat render gagal). */
    private string $lastChromeError = '';

    /**
     * Render dokumen menjadi PDF secara ter-batch, lalu gabungkan kembali.
     *
     * Menerima salah satu dari:
     * - `head` (CSS bersama) + `pages` (array HTML per halaman), atau
     * - `html` (dokumen utuh) untuk kompatibilitas lama.
     */
    public function store(Request $request): Response
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

        $bin = find_chromium_binary();
        if ($bin === null) {
            return response()->json(['error' => 'Chrome/Edge/Chromium tidak ditemukan di sistem.'], 500);
        }

        $dir = storage_path('app/export');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $base = 'export_'.uniqid('', true);
        $profileDir = $dir.DIRECTORY_SEPARATOR.'profile_'.$base;
        $mergedPath = $dir.DIRECTORY_SEPARATOR.$base.'.pdf';
        $pdfPaths = [];

        try {
            // Tanpa alat penggabung (mis. lokal Windows), render sekali penuh.
            $chunks = $this->canMerge()
                ? array_chunk($pages, self::BATCH_SIZE)
                : [$pages];

            foreach ($chunks as $index => $chunk) {
                $doc = $head === '' ? $chunk[0] : $this->buildDocument($head, $chunk);
                $pdf = $this->renderHtml($bin, $dir, $base, $profileDir, $doc, $index);
                if ($pdf === null) {
                    $detail = $this->lastChromeError !== ''
                        ? ': '.$this->lastChromeError
                        : '';
                    return response()->json([
                        'error' => 'Gagal membuat PDF pada bagian '.($index + 1).$detail,
                    ], 500);
                }
                $pdfPaths[] = $pdf;
            }

            $content = count($pdfPaths) === 1
                ? (string) file_get_contents($pdfPaths[0])
                : $this->mergePdfs($mergedPath, $pdfPaths);

            record_audit($request, 'export_pdf', [
                'project' => $request->input('project', null),
                'format' => $request->input('format', 'pdf'),
                'pages' => count($pages),
            ]);

            return response($content, 200, ['Content-Type' => 'application/pdf']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Gagal membuat PDF: '.$e->getMessage()], 500);
        } finally {
            foreach ($pdfPaths as $pdf) {
                @unlink($pdf);
            }
            @unlink($mergedPath);
            remove_tree($profileDir);
        }
    }

    /**
     * Susun dokumen HTML utuh dari head CSS + kumpulan halaman.
     */
    private function buildDocument(string $head, array $pages): string
    {
        return '<!doctype html><html><head>'.$head.'</head><body><div class="print-only">'.implode('', $pages).'</div></body></html>';
    }

    /**
     * Render satu dokumen HTML menjadi PDF via Chrome headless.
     */
    private function renderHtml(string $bin, string $dir, string $base, string $profileDir, string $doc, int $index): ?string
    {
        $suffix = str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        $htmlPath = $dir.DIRECTORY_SEPARATOR.$base.'_'.$suffix.'.html';
        $pdfPath = $dir.DIRECTORY_SEPARATOR.$base.'_'.$suffix.'.pdf';

        file_put_contents($htmlPath, $doc);
        $fileUrl = 'file:///'.ltrim(str_replace('\\', '/', $htmlPath), '/');

        $args = [
            $bin,
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--no-zygote',
            '--disable-crash-reporter',
            '--hide-scrollbars',
            '--no-pdf-header-footer',
            '--virtual-time-budget=10000',
            '--user-data-dir='.$profileDir,
            '--print-to-pdf='.$pdfPath,
            $fileUrl,
        ];

        $this->runChrome($args);

        // Fallback untuk Chrome lama yang belum mengenali `--headless=new`.
        if (! is_file($pdfPath)) {
            $args[1] = '--headless';
            $this->runChrome($args);
        }

        @unlink($htmlPath);

        return is_file($pdfPath) ? $pdfPath : null;
    }

    /**
     * Jalankan Chrome headless. Bila proses terhenti oleh sinyal/timeout,
     * biarkan pemanggil yang memeriksa keberadaan file PDF hasilnya.
     */
    private function runChrome(array $args): void
    {
        $process = new Process($args);
        $process->setTimeout(120);
        try {
            $process->run();
        } catch (ProcessRuntimeException $e) {
            // Proses terhenti oleh sinyal/timeout (mis. SIGTRAP). Diabaikan di sini.
            $this->lastChromeError = 'proses terhenti: '.$e->getMessage();
        }

        $error = trim($process->getErrorOutput());
        if ($error !== '') {
            $this->lastChromeError = $error;
        } else {
            $out = trim($process->getOutput());
            if ($out !== '') {
                $this->lastChromeError = $out;
            }
        }
    }

    /**
     * Cek apakah ada alat penggabung PDF (pdfunite / ghostscript) di sistem.
     */
    private function canMerge(): bool
    {
        return $this->resolveTool(['/usr/bin/pdfunite', '/usr/local/bin/pdfunite'], ['pdfunite']) !== null
            || $this->resolveTool(['/usr/bin/gs', '/usr/local/bin/gs'], ['gs']) !== null;
    }

    /**
     * Gabungkan beberapa PDF menjadi satu (pdfunite, lalu fallback ghostscript).
     */
    private function mergePdfs(string $outPath, array $pdfPaths): string
    {
        $pdfunite = $this->resolveTool(['/usr/bin/pdfunite', '/usr/local/bin/pdfunite'], ['pdfunite']);
        if ($pdfunite !== null) {
            $process = new Process(array_merge([$pdfunite], $pdfPaths, [$outPath]));
            $process->setTimeout(300);
            $this->runMerge($process);
            if (is_file($outPath)) {
                return (string) file_get_contents($outPath);
            }
            @unlink($outPath);
        }

        $gs = $this->resolveTool(['/usr/bin/gs', '/usr/local/bin/gs'], ['gs']);
        if ($gs !== null) {
            $args = array_merge(
                [$gs, '-dBATCH', '-dNOPAUSE', '-q', '-sDEVICE=pdfwrite', '-sOutputFile='.$outPath],
                $pdfPaths,
            );
            $process = new Process($args);
            $process->setTimeout(300);
            $this->runMerge($process);
            if (is_file($outPath)) {
                return (string) file_get_contents($outPath);
            }
        }

        throw new \RuntimeException('Alat penggabung PDF (pdfunite / ghostscript) tidak tersedia di server.');
    }

    /**
     * Jalankan proses penggabungan dan tangkap kegagalan sinyal/timeout.
     */
    private function runMerge(Process $process): void
    {
        try {
            $process->run();
        } catch (ProcessRuntimeException $e) {
            // Proses terhenti; keberhasilan ditentukan lewat keberadaan file output.
        }
    }

    /**
     * Cari binary tool dari path yang umum, lalu fallback ke `which`.
     */
    private function resolveTool(array $paths, array $commands): ?string
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        foreach ($commands as $command) {
            $found = resolve_command($command);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
