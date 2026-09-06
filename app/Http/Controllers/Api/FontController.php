<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Font;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FontController extends Controller
{
    private const MAX_FONT_KB = 10240; // 10 MB

    private const FORMAT_BY_EXT = [
        'ttf' => 'truetype',
        'otf' => 'opentype',
        'woff' => 'woff',
        'woff2' => 'woff2',
    ];

    /**
     * Daftar font kustom milik user, terbaru di depan.
     */
    public function index(Request $request): JsonResponse
    {
        $fonts = Font::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Font $f) => $this->present($f));

        return response()->json(['fonts' => $fonts]);
    }

    /**
     * Simpan font kustom (TTF/OTF/WOFF/WOFF2) ke object storage.
     */
    public function store(Request $request): JsonResponse
    {
        $file = $request->file('file');

        if (! $file) {
            return response()->json(['error' => 'File font wajib diunggah.'], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! isset(self::FORMAT_BY_EXT[$ext])) {
            return response()->json(['error' => 'Format font tidak didukung (gunakan .ttf, .otf, .woff, atau .woff2).'], 422);
        }

        if ($file->getSize() > self::MAX_FONT_KB * 1024) {
            return response()->json(['error' => 'Ukuran font melebihi 10 MB.'], 422);
        }

        $user = $request->user();
        $uuid = (string) Str::uuid();
        $name = $uuid.'.'.$ext;
        $dir = 'fonts/'.$user->uuid;

        $path = Storage::disk('s3')->putFileAs($dir, $file, $name);
        if ($path === false) {
            return response()->json(['error' => 'Gagal menyimpan font ke object storage.'], 500);
        }

        $font = Font::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'family' => $this->familyFromName($file->getClientOriginalName()),
            'format' => self::FORMAT_BY_EXT[$ext],
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'path' => $path,
        ]);

        return response()->json(['font' => $this->present($font)], 201);
    }

    /**
     * Hapus font kustom milik user.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $font = Font::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $font) {
            return response()->json(['error' => 'Font tidak ditemukan.'], 404);
        }

        Storage::disk('s3')->delete($font->path);
        $font->delete();

        return response()->json(['message' => 'Font dihapus.']);
    }

    private function present(Font $font): array
    {
        return [
            'id' => $font->uuid,
            'family' => $font->family,
            'format' => $font->format,
            'dataUrl' => $this->dataUrl($font),
        ];
    }

    private function dataUrl(Font $font): ?string
    {
        $disk = Storage::disk('s3');
        if (! $disk->exists($font->path)) {
            return null;
        }

        $mime = $font->mime ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($font->path));
    }

    private function familyFromName(string $name): string
    {
        $base = trim(preg_replace('/\.[^.]+$/', '', $name) ?: '') ?: 'Font Kustom';
        $family = preg_replace('/[^\w\s-]/u', '', $base);

        return trim($family ?? '') ?: 'Font Kustom';
    }
}
