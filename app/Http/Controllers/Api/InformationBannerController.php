<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformationBannerController extends Controller
{
    private const KEY = 'information_banner';

    private const DEFAULTS = [
        'enabled' => false,
        'mode' => 'single', // 'single' | 'marquee'
        'text' => '',
        'link_text' => '',
        'link_url' => '',
        'messages' => [],
        'background' => '#171717',
        'text_color' => '#ffffff',
        'speed' => 40, // detik per putaran penuh marquee (semakin besar = semakin lambat)
    ];

    /**
     * Banner informasi aktif untuk ditampilkan di atas header homepage (publik).
     */
    public function show(Request $request): JsonResponse
    {
        $banner = $this->resolve();

        if (empty($banner['enabled'])) {
            return response()->json(['banner' => null]);
        }

        $messages = $this->activeMessages($banner);

        if (empty($messages)) {
            return response()->json(['banner' => null]);
        }

        return response()->json([
            'banner' => [
                'enabled' => true,
                'mode' => $banner['mode'] === 'marquee' ? 'marquee' : 'single',
                'background' => $this->safeColor($banner['background'], '#171717'),
                'text_color' => $this->safeColor($banner['text_color'], '#ffffff'),
                'speed' => max(5, (int) ($banner['speed'] ?? 40)),
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Data pengaturan banner informasi untuk halaman admin.
     */
    public function settings(Request $request): JsonResponse
    {
        return response()->json([
            'banner' => $this->resolve(),
            'defaults' => self::DEFAULTS,
        ]);
    }

    /**
     * Simpan pengaturan banner informasi.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'mode' => ['sometimes', 'string', 'in:single,marquee'],
            'text' => ['nullable', 'string', 'max:500'],
            'link_text' => ['nullable', 'string', 'max:60'],
            'link_url' => ['nullable', 'string', 'max:191'],
            'messages' => ['sometimes', 'array', 'max:20'],
            'messages.*.text' => ['nullable', 'string', 'max:500'],
            'messages.*.link_text' => ['nullable', 'string', 'max:60'],
            'messages.*.link_url' => ['nullable', 'string', 'max:191'],
            'background' => ['nullable', 'string', 'max:30'],
            'text_color' => ['nullable', 'string', 'max:30'],
            'speed' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        $banner = array_replace($this->resolve(), $data);

        // Bersihkan pesan marquee dari entri yang kosong seluruhnya.
        if (isset($data['messages'])) {
            $banner['messages'] = array_values(array_filter(array_map(function ($m) {
                $m = is_array($m) ? $m : [];
                $text = trim((string) ($m['text'] ?? ''));
                if ($text === '') {
                    return null;
                }

                return [
                    'text' => $text,
                    'link_text' => trim((string) ($m['link_text'] ?? '')),
                    'link_url' => trim((string) ($m['link_url'] ?? '')),
                ];
            }, $data['messages'])));
        }

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $banner]);

        return response()->json([
            'message' => 'Banner informasi berhasil disimpan.',
            'banner' => $this->resolve(),
        ]);
    }

    private function resolve(): array
    {
        $setting = Setting::where('key', self::KEY)->first();
        $stored = $setting ? $setting->value : [];

        return array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    /**
     * Daftar pesan aktif sesuai mode banner.
     */
    private function activeMessages(array $banner): array
    {
        if (($banner['mode'] ?? 'single') === 'marquee') {
            return array_values(array_filter(array_map(function ($m) {
                $m = is_array($m) ? $m : [];
                $text = trim((string) ($m['text'] ?? ''));
                if ($text === '') {
                    return null;
                }

                return [
                    'text' => $text,
                    'link_text' => trim((string) ($m['link_text'] ?? '')),
                    'link_url' => trim((string) ($m['link_url'] ?? '')),
                ];
            }, is_array($banner['messages'] ?? null) ? $banner['messages'] : [])));
        }

        $text = trim((string) ($banner['text'] ?? ''));
        if ($text === '') {
            return [];
        }

        return [[
            'text' => $text,
            'link_text' => trim((string) ($banner['link_text'] ?? '')),
            'link_url' => trim((string) ($banner['link_url'] ?? '')),
        ]];
    }

    /**
     * Pastikan warna berupa hex valid; fallback ke default bila tidak.
     */
    private function safeColor(mixed $value, string $default): string
    {
        $value = trim((string) $value);

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) ? $value : $default;
    }
}
