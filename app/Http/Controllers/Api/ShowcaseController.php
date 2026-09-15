<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShowcaseImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShowcaseController extends Controller
{
    private const MAX_IMAGE_KB = 2048; // 2 MB

    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Gambar bawaan bila admin belum mengunggah apa pun.
     */
    private const DEFAULTS = [
        [
            'src' => '/img/Builder.PNG',
            'alt' => 'Editor blok Tulissin',
            'caption' => 'Susun bab dengan blok',
        ],
        [
            'src' => '/img/Builder-canvas.PNG',
            'alt' => 'Canvas dokumen Tulissin',
            'caption' => 'Tulis di canvas dengan format otomatis',
        ],
        [
            'src' => '/img/Workspace.PNG',
            'alt' => 'Workspace Tulissin',
            'caption' => 'Kelola semua dokumen di satu tempat',
        ],
    ];

    /**
     * Daftar gambar tampilan yang sudah tersimpan (untuk halaman admin).
     */
    public function index(Request $request): JsonResponse
    {
        $stored = self::stored();

        return response()->json([
            'screenshots' => $stored,
            'using_defaults' => $stored === [],
            'defaults' => self::DEFAULTS,
        ]);
    }

    /**
     * Unggah gambar baru ke S3 (folder showcase/) dan catat ke database.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:'.self::MAX_IMAGE_KB],
            'alt' => ['nullable', 'string', 'max:191'],
            'caption' => ['nullable', 'string', 'max:191'],
        ]);

        $file = $data['file'];
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return response()->json(['error' => 'Format gambar tidak didukung (jpg/png/gif/webp).'], 422);
        }

        $uuid = (string) Str::uuid();
        $name = $uuid.'.'.$ext;
        $path = Storage::disk('s3')->putFileAs('showcase', $file, $name);

        if ($path === false) {
            return response()->json(['error' => 'Gagal menyimpan gambar ke object storage.'], 500);
        }

        $maxOrder = (int) ShowcaseImage::max('sort_order');

        $image = ShowcaseImage::create([
            'uuid' => $uuid,
            'mime' => $file->getMimeType() ?: 'image/'.$ext,
            'path' => $path,
            'alt' => trim((string) ($data['alt'] ?? '')),
            'caption' => trim((string) ($data['caption'] ?? '')),
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(self::format($image), 201);
    }

    /**
     * Simpan urutan, alt, dan caption seluruh gambar sekaligus.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screenshots' => ['present', 'array', 'max:30'],
            'screenshots.*.id' => ['required', 'string', 'uuid'],
            'screenshots.*.alt' => ['nullable', 'string', 'max:191'],
            'screenshots.*.caption' => ['nullable', 'string', 'max:191'],
        ]);

        foreach ($data['screenshots'] as $i => $item) {
            ShowcaseImage::where('uuid', $item['id'])->update([
                'alt' => trim((string) ($item['alt'] ?? '')),
                'caption' => trim((string) ($item['caption'] ?? '')),
                'sort_order' => $i,
            ]);
        }

        return response()->json([
            'message' => 'Tampilan homepage berhasil disimpan.',
            'screenshots' => self::stored(),
            'using_defaults' => self::stored() === [],
        ]);
    }

    /**
     * Hapus gambar (record + file di S3).
     */
    public function destroy(Request $request, string $showcase): JsonResponse
    {
        $image = ShowcaseImage::where('uuid', $showcase)->first();

        if (! $image) {
            return response()->json(['error' => 'Gambar tidak ditemukan.'], 404);
        }

        Storage::disk('s3')->delete($image->path);
        $image->delete();

        return response()->json([
            'message' => 'Gambar dihapus.',
            'screenshots' => self::stored(),
            'using_defaults' => self::stored() === [],
        ]);
    }

    /**
     * Tampilkan file gambar secara publik (dipakai <img> di homepage).
     */
    public function show(string $uuid)
    {
        $image = ShowcaseImage::where('uuid', $uuid)->first();

        if (! $image) {
            return response()->json(['error' => 'Gambar tidak ditemukan.'], 404);
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($image->path)) {
            return response()->json(['error' => 'Gambar tidak ditemukan.'], 404);
        }

        return response()->stream(function () use ($disk, $image) {
            $stream = $disk->readStream($image->path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, ['Content-Type' => $image->mime ?: 'application/octet-stream']);
    }

    /**
     * Daftar yang tampil di homepage: pakai hasil unggahan admin,
     * atau gambar bawaan bila belum ada yang diunggah.
     */
    public static function resolve(): array
    {
        $stored = self::stored();

        return $stored === [] ? self::DEFAULTS : $stored;
    }

    private static function stored(): array
    {
        return ShowcaseImage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ShowcaseImage $image) => self::format($image))
            ->values()
            ->all();
    }

    private static function format(ShowcaseImage $image): array
    {
        return [
            'id' => $image->uuid,
            'src' => '/api/showcase/'.$image->uuid,
            'alt' => (string) ($image->alt ?? ''),
            'caption' => (string) ($image->caption ?? ''),
        ];
    }
}
