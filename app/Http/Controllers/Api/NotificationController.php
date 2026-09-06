<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlastImage;
use App\Models\User;
use App\Notifications\BroadcastEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    private const MAX_BLAST_IMAGE_KB = 2048; // 2 MB

    /**
     * Kirim email broadcast (promo, pengumuman, info, dsb.) ke seluruh atau sebagian pengguna.
     */
    public function emailBlast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:100000'],
            'all' => ['sometimes', 'boolean'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $subject = (string) $data['subject'];
        $title = (string) ($data['title'] ?? '');
        $message = (string) $data['message'];

        $query = User::whereNotNull('email');

        if (! $request->boolean('all')) {
            $ids = array_values(array_filter(
                (array) ($data['user_ids'] ?? []),
                fn ($id) => is_int($id) || ctype_digit((string) $id),
            ));

            if ($ids === []) {
                return response()->json(['error' => 'Pilih setidaknya satu penerima.'], 422);
            }

            $query->whereIn('id', $ids);
        }

        $users = $query->get();

        Notification::send($users, new BroadcastEmailNotification($subject, $title, $message));

        return response()->json([
            'message' => 'Email masuk antrian pengiriman.',
            'recipients' => $users->count(),
        ]);
    }

    /**
     * Daftar pengguna yang bisa menerima email broadcast.
     */
    public function broadcastRecipients(): JsonResponse
    {
        $users = User::whereNotNull('email')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }

    /**
     * Unggah gambar untuk body email broadcast ke object storage (folder blast_email/).
     */
    public function uploadBlastImage(Request $request): JsonResponse
    {
        $file = $request->file('file');

        if (! $file) {
            return response()->json(['error' => 'File gambar wajib diunggah.'], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return response()->json(['error' => 'Format gambar tidak didukung (jpg/png/gif/webp).'], 422);
        }

        if ($file->getSize() > self::MAX_BLAST_IMAGE_KB * 1024) {
            return response()->json(['error' => 'Ukuran gambar melebihi 2 MB.'], 422);
        }

        $uuid = (string) Str::uuid();
        $name = $uuid.'.'.$ext;
        $path = Storage::disk('s3')->putFileAs('blast_email', $file, $name);

        if ($path === false) {
            return response()->json(['error' => 'Gagal menyimpan gambar ke object storage.'], 500);
        }

        BlastImage::create([
            'uuid' => $uuid,
            'mime' => $file->getMimeType() ?: 'image/'.$ext,
            'path' => $path,
        ]);

        return response()->json(['url' => url('/api/blast-images/'.$uuid)], 201);
    }

    /**
     * Tampilkan gambar broadcast secara publik (dipakai di dalam email).
     */
    public function showBlastImage(string $uuid)
    {
        $image = BlastImage::where('uuid', $uuid)->first();

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
}
