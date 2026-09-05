<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Helper global untuk proses ekspor PDF (tanpa package eksternal).
 * Diletakkan di sini (bukan di routes/api.php) supaya tidak terjadi
 * error "Cannot redeclare" saat `php artisan route:cache`.
 */

if (! function_exists('find_chromium_binary')) {
    function find_chromium_binary(): ?string
    {
        // Hormati konfigurasi lingkungan (umum di VPS/Docker/CI).
        $env = getenv('CHROME_BIN') ?: getenv('CHROMIUM_BIN') ?: getenv('PUPPETEER_EXECUTABLE_PATH');
        if ($env && is_file($env)) {
            return $env;
        }

        $candidates = [
            // Windows
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            // Linux (VPS)
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/microsoft-edge',
            '/snap/bin/chromium',
            // macOS
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
        ];

        $localAppData = getenv('LOCALAPPDATA');
        if ($localAppData) {
            $candidates[] = $localAppData.'\\Google\\Chrome\\Application\\chrome.exe';
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Fallback: cari binary lewat PATH (umum di Linux).
        foreach (['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'microsoft-edge', 'msedge'] as $bin) {
            $found = resolve_command($bin);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}

if (! function_exists('resolve_command')) {
    function resolve_command(string $cmd): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }

        $output = [];
        $code = 0;
        $locator = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        @exec($locator.' '.escapeshellarg($cmd).' 2>/dev/null', $output, $code);

        if ($code === 0 && ! empty($output[0]) && is_string($output[0])) {
            $path = trim($output[0]);

            return $path !== '' ? $path : null;
        }

        return null;
    }
}

if (! function_exists('record_audit')) {
    function record_audit(Request $request, string $action, array $after): void
    {
        $user = $request->user();

        DB::table('audit_logs')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'action' => $action,
            'model_type' => 'export',
            'model_id' => null,
            'before' => null,
            'after' => json_encode($after),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

if (! function_exists('remove_tree')) {
    function remove_tree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
