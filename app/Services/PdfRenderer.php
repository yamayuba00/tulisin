<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

/**
 * Render dokumen HTML menjadi PDF via Chrome/Edge/Chromium headless.
 *
 * Dipisah dari controller agar bisa dipanggil dari antrian (queue) tanpa
 * menahan request HTTP. Gambar media diunduh ke file lokal sementara (bukan
 * di-embed sebagai base64) supaya memori tidak melonjak saat dokumen bergambar.
 */
class PdfRenderer
{
    /**
     * Jumlah halaman per batch render Chrome.
     * Makin kecil makin ringan tiap proses, makin besar makin cepat totalnya.
     */
    private const BATCH_SIZE = 5;

    /** Pesan error terakhir dari Chrome (untuk diagnostik saat render gagal). */
    private string $lastChromeError = '';

    /**
     * Render kumpulan halaman HTML menjadi satu PDF, tulis hasilnya ke $outPath.
     */
    public function render(string $outPath, array $pages, string $head = ''): void
    {
        $dir = storage_path('app/export');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $base = 'export_'.uniqid('', true);
        $imagesDir = $dir.DIRECTORY_SEPARATOR.$base.'_images';
        if (! is_dir($imagesDir)) {
            mkdir($imagesDir, 0777, true);
        }
        $profileDir = $dir.DIRECTORY_SEPARATOR.'profile_'.$base;
        $homeDir = $dir.DIRECTORY_SEPARATOR.'home_'.$base;
        if (! is_dir($homeDir)) {
            mkdir($homeDir, 0777, true);
        }
        $mergedPath = $dir.DIRECTORY_SEPARATOR.$base.'.pdf';
        $pdfPaths = [];

        // Gambar disimpan sebagai file lokal (bukan data URI) agar hemat memori.
        $pages = array_map(fn (string $p) => $this->embedImages($p, $imagesDir), $pages);

        try {
            $bin = find_chromium_binary();
            if ($bin === null) {
                throw new \RuntimeException('Chrome/Edge/Chromium tidak ditemukan di sistem.');
            }

            // Tanpa alat penggabung (mis. lokal Windows), render sekali penuh.
            $chunks = $this->canMerge()
                ? array_chunk($pages, self::BATCH_SIZE)
                : [$pages];

            foreach ($chunks as $index => $chunk) {
                $doc = $head === '' ? $chunk[0] : $this->buildDocument($head, $chunk);
                $pdf = $this->renderHtml($bin, $dir, $base, $profileDir, $homeDir, $doc, $index);
                if ($pdf === null) {
                    $detail = $this->lastChromeError !== '' ? ': '.$this->lastChromeError : '';
                    throw new \RuntimeException('Gagal membuat PDF pada bagian '.($index + 1).$detail);
                }
                $pdfPaths[] = $pdf;
            }

            if (count($pdfPaths) === 1) {
                // Salin langsung (tanpa membaca seluruh isi ke memori).
                copy($pdfPaths[0], $outPath);
            } else {
                $this->mergePdfs($outPath, $pdfPaths);
            }
        } finally {
            foreach ($pdfPaths as $pdf) {
                @unlink($pdf);
            }
            @unlink($mergedPath);
            remove_tree($profileDir);
            remove_tree($homeDir);
            remove_tree($imagesDir);
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
     * Ganti `src` gambar media (`/api/media/files/{uuid}`) menjadi path file
     * lokal sehingga Chrome bisa memuatnya tanpa sesi login dan tanpa base64.
     */
    private function embedImages(string $html, string $imagesDir): string
    {
        $result = preg_replace_callback(
            '/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(.*?)\2/is',
            function (array $m) use ($imagesDir): string {
                $local = $this->imageToLocalFile($m[3], $imagesDir);

                return $m[1].$m[2].($local ?? $m[3]).$m[2];
            },
            $html,
        );

        return $result ?? $html;
    }

    /**
     * Unduh satu gambar dari object storage ke file lokal, kembalikan URL `file://`.
     */
    private function imageToLocalFile(string $src, string $imagesDir): ?string
    {
        if (! preg_match('#/api/media/(?:files/)?([0-9a-fA-F-]{36})#', $src, $m)) {
            return null;
        }

        $media = Media::where('uuid', $m[1])->first();
        if (! $media) {
            return null;
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($media->path)) {
            return null;
        }

        $ext = strtolower((string) pathinfo($media->path, PATHINFO_EXTENSION));
        if ($ext === '' || strlen($ext) > 5) {
            $ext = match (true) {
                str_starts_with((string) $media->mime, 'image/png') => 'png',
                str_starts_with((string) $media->mime, 'image/jpeg') => 'jpg',
                str_starts_with((string) $media->mime, 'image/gif') => 'gif',
                str_starts_with((string) $media->mime, 'image/webp') => 'webp',
                str_starts_with((string) $media->mime, 'image/svg') => 'svg',
                default => 'png',
            };
        }

        $local = $imagesDir.DIRECTORY_SEPARATOR.$media->uuid.'.'.$ext;
        if (! is_file($local)) {
            $stream = $disk->readStream($media->path);
            if (is_resource($stream)) {
                file_put_contents($local, $stream);
                fclose($stream);
            } else {
                // Fallback: baca penuh bila readStream tidak tersedia.
                file_put_contents($local, $disk->get($media->path));
            }
        }

        return 'file:///'.ltrim(str_replace('\\', '/', $local), '/');
    }

    /**
     * Render satu dokumen HTML menjadi PDF via Chrome headless.
     */
    private function renderHtml(string $bin, string $dir, string $base, string $profileDir, string $homeDir, string $doc, int $index): ?string
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
            '--allow-file-access-from-files',
            '--no-pdf-header-footer',
            '--virtual-time-budget=10000',
            '--user-data-dir='.$profileDir,
            '--print-to-pdf='.$pdfPath,
            $fileUrl,
        ];

        $this->runChrome($args, $homeDir);

        // Fallback untuk Chrome lama yang belum mengenali `--headless=new`.
        if (! is_file($pdfPath)) {
            $args[1] = '--headless';
            $this->runChrome($args, $homeDir);
        }

        @unlink($htmlPath);

        return is_file($pdfPath) ? $pdfPath : null;
    }

    /**
     * Jalankan Chrome headless. Bila proses terhenti oleh sinyal/timeout,
     * biarkan pemanggil yang memeriksa keberadaan file PDF hasilnya.
     */
    private function runChrome(array $args, string $homeDir): void
    {
        $process = new Process($args);
        $process->setTimeout(120);
        // Chrome menulis direktori XDG (`.local`, `.config`, `.cache`) ke $HOME.
        // Di VPS, HOME user PHP-FPM (www-data) = /var/www yang tidak boleh ditulis,
        // sehingga muncul "mkdir /var/www/.local: Permission denied". Arahkan HOME
        // ke folder sementara yang bisa ditulis agar render tidak gagal.
        $process->setEnv([
            'HOME' => $homeDir,
            'XDG_CONFIG_HOME' => $homeDir.DIRECTORY_SEPARATOR.'.config',
            'XDG_CACHE_HOME' => $homeDir.DIRECTORY_SEPARATOR.'.cache',
            'XDG_DATA_HOME' => $homeDir.DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'share',
        ]);

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
     * Gabungkan beberapa PDF menjadi satu file output (pdfunite, lalu ghostscript).
     */
    private function mergePdfs(string $outPath, array $pdfPaths): void
    {
        $pdfunite = $this->resolveTool(['/usr/bin/pdfunite', '/usr/local/bin/pdfunite'], ['pdfunite']);
        if ($pdfunite !== null) {
            $process = new Process(array_merge([$pdfunite], $pdfPaths, [$outPath]));
            $process->setTimeout(300);
            $this->runMerge($process);
            if (is_file($outPath)) {
                return;
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
                return;
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
