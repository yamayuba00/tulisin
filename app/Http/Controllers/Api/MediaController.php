<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private const MAX_IMAGE_KB = 2048; // 2 MB

    /**
     * Daftar media (gambar) milik user saat ini, terbaru di depan.
     */
    public function index(Request $request): JsonResponse
    {
        $media = Media::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Media $m) => $this->format($m));

        return response()->json($media);
    }

    /**
     * Simpan gambar ke object storage (folder file_manager/{uuidUser}),
     * catat metadata-nya di tabel `media`.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:'.self::MAX_IMAGE_KB],
        ]);

        $file = $data['file'];
        $user = $request->user();

        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension() ?: $file->extension();
        $name = $uuid.($ext ? '.'.$ext : '');
        $dir = 'file_manager/'.$user->uuid;

        $path = Storage::disk('s3')->putFileAs($dir, $file, $name);
        if ($path === false) {
            return response()->json(['error' => 'Gagal menyimpan file ke object storage.'], 500);
        }

        $media = Media::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ]);

        return response()->json($this->format($media), 201);
    }

    /**
     * Tampilkan file media (inline) dari object storage.
     */
    public function show(Request $request, string $id)
    {
        $media = Media::where('user_id', $request->user()->id)
            ->where('uuid', $id)
            ->first();

        if (! $media) {
            return response()->json(['error' => 'File tidak ditemukan.'], 404);
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($media->path)) {
            return response()->json(['error' => 'File tidak ditemukan.'], 404);
        }

        return response()->stream(function () use ($disk, $media) {
            $stream = $disk->readStream($media->path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, ['Content-Type' => $media->mime ?: 'application/octet-stream']);
    }

    /**
     * Tampilkan file media publik (untuk dokumen yang dibagikan, tanpa login).
     */
    public function publicShow(string $id)
    {
        $media = Media::where('uuid', $id)->first();

        if (! $media) {
            return response()->json(['error' => 'File tidak ditemukan.'], 404);
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($media->path)) {
            return response()->json(['error' => 'File tidak ditemukan.'], 404);
        }

        return response()->stream(function () use ($disk, $media) {
            $stream = $disk->readStream($media->path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, ['Content-Type' => $media->mime ?: 'application/octet-stream']);
    }

    /**
     * Hapus media milik user (file + record).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $media = Media::where('user_id', $request->user()->id)
            ->where('uuid', $id)
            ->first();

        if (! $media) {
            return response()->json(['error' => 'File tidak ditemukan.'], 404);
        }

        Storage::disk('s3')->delete($media->path);
        $media->delete();

        return response()->json(['message' => 'File dihapus.']);
    }

    private function format(Media $media): array
    {
        return [
            'id' => $media->uuid,
            'name' => $media->name,
            'mime' => $media->mime,
            'size' => $media->size,
            'url' => '/api/media/files/'.$media->uuid,
        ];
    }
}
