<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastEmailJob;
use App\Models\BlastImage;
use App\Models\EmailBroadcast;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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

        if ($users->isEmpty()) {
            return response()->json(['error' => 'Tidak ada penerima email.'], 422);
        }

        $broadcast = EmailBroadcast::create([
            'subject' => $subject,
            'title' => $title,
            'recipients_total' => $users->count(),
            'status' => 'queued',
        ]);

        $jobs = $users->map(
            fn (User $user) => new SendBroadcastEmailJob($user, $subject, $title, $message),
        )->all();

        $batch = Bus::batch($jobs)
            ->name('Email Broadcast #'.$broadcast->id)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($broadcast) {
                $processed = $batch->totalJobs - $batch->pendingJobs;
                $failed = $batch->failedJobs;

                $status = $batch->cancelled()
                    ? 'failed'
                    : ($failed === 0 ? 'completed' : 'partial');

                $broadcast->update([
                    'processed' => $processed,
                    'failed' => $failed,
                    'status' => $status,
                ]);
            })
            ->dispatch();

        $broadcast->update(['batch_id' => $batch->id]);

        return response()->json([
            'message' => 'Email masuk antrian pengiriman.',
            'recipients' => $users->count(),
            'broadcast_id' => $broadcast->id,
        ]);
    }

    /**
     * Riwayat email broadcast (dengan status antrian) — untuk tabel + polling.
     */
    public function emailBroadcasts(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 100);
        $page = max($request->integer('page', 1), 1);

        $paginator = EmailBroadcast::query()
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $batchIds = collect($paginator->items())
            ->pluck('batch_id')
            ->filter()
            ->unique()
            ->all();

        $batches = [];
        if ($batchIds !== []) {
            $batches = DB::table('job_batches')
                ->whereIn('id', $batchIds)
                ->get()
                ->keyBy('id');
        }

        $items = collect($paginator->items())->map(function (EmailBroadcast $broadcast) use ($batches) {
            $total = $broadcast->recipients_total;
            $processed = $broadcast->processed;
            $failed = $broadcast->failed;
            $status = $broadcast->status;

            $batch = $broadcast->batch_id ? ($batches[$broadcast->batch_id] ?? null) : null;

            if ($batch) {
                $total = (int) $batch->total_jobs;
                $failed = (int) $batch->failed_jobs;
                $processed = $total - (int) $batch->pending_jobs;

                if ($batch->finished_at) {
                    $status = $failed > 0 ? 'partial' : 'completed';
                } elseif ($batch->cancelled_at) {
                    $status = 'failed';
                } else {
                    $status = ((int) $batch->pending_jobs === $total) ? 'queued' : 'processing';
                }
            }

            return [
                'id' => $broadcast->id,
                'subject' => $broadcast->subject,
                'title' => $broadcast->title,
                'recipients_total' => (int) $total,
                'processed' => (int) $processed,
                'failed' => (int) $failed,
                'status' => $status,
                'created_at' => $broadcast->created_at?->toISOString(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
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
