<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SharedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SharedDocumentController extends Controller
{
    /**
     * Simpan snapshot dokumen agar bisa dibagikan lewat URL publik.
     * Link berlaku selama time_view menit (default 24 jam = 1440 menit).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payload' => ['required', 'array'],
            'time_view' => ['sometimes', 'integer', 'min:1', 'max:10080'],
            'project_uuid' => ['sometimes', 'nullable', 'string', 'max:36'],
        ]);

        $timeView = $data['time_view'] ?? 1440;

        $share = SharedDocument::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'payload' => $data['payload'],
            'project_uuid' => $data['project_uuid'] ?? null,
            'state' => Str::random(40),
            'time_view' => $timeView,
            'expires_at' => now()->addMinutes($timeView),
        ]);

        return $this->shareResponse($share, 201);
    }

    /**
     * Ambil dokumen yang dibagikan — publik, tanpa login.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $share = SharedDocument::where('uuid', $uuid)->first();

        if (! $share) {
            return response()->json(['error' => 'Dokumen tidak ditemukan atau sudah tidak dibagikan.'], 404);
        }

        if ($share->state && $request->query('state') !== $share->state) {
            return response()->json(['error' => 'Link tidak valid.'], 404);
        }

        if ($share->expires_at && $share->expires_at->isPast()) {
            return response()->json(['error' => 'Link sudah kedaluwarsa. Silakan minta link baru.'], 410);
        }

        // Bila link terhubung ke project, ambil data terbaru langsung dari DB
        // sehingga perubahan builder otomatis tampil tanpa perlu snapshot manual.
        $name = $share->name;
        $payload = $share->payload;

        if ($share->project_uuid) {
            $project = Project::where('uuid', $share->project_uuid)->first();
            if ($project && $project->payload) {
                $projectEdited = $project->payload['lastEdited'] ?? null;
                $snapshotEdited = is_array($payload) ? ($payload['lastEdited'] ?? null) : null;

                // Pakai data project bila lebih baru (atau snapshot tidak punya penanda),
                // supaya snapshot yang baru dibuat sebelum autosave tidak tertimpa data lama.
                if ($projectEdited === null || $snapshotEdited === null || $projectEdited >= $snapshotEdited) {
                    $name = $project->title ?: $name;
                    $payload = $project->payload;
                }
            }
        }

        return response()->json([
            'name' => $name,
            'payload' => $payload,
            'timeView' => $share->time_view,
            'expiresAt' => $share->expires_at?->toISOString(),
        ]);
    }

    /**
     * Perbarui snapshot dokumen yang sudah dibagikan (perpanjang masa berlaku).
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $share = SharedDocument::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $share) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payload' => ['required', 'array'],
            'time_view' => ['sometimes', 'integer', 'min:1', 'max:10080'],
            'project_uuid' => ['sometimes', 'nullable', 'string', 'max:36'],
        ]);

        $timeView = $data['time_view'] ?? $share->time_view ?? 1440;

        $share->update([
            'name' => $data['name'],
            'payload' => $data['payload'],
            'project_uuid' => $data['project_uuid'] ?? $share->project_uuid,
            'time_view' => $timeView,
            'expires_at' => now()->addMinutes($timeView),
        ]);

        return $this->shareResponse($share);
    }

    /**
     * Berhenti membagikan dokumen.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $share = SharedDocument::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $share) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $share->delete();

        return response()->json(['message' => 'Link bagikan telah dinonaktifkan.']);
    }

    protected function shareResponse(SharedDocument $share, int $status = 200): JsonResponse
    {
        return response()->json([
            'uuid' => $share->uuid,
            'state' => $share->state,
            'timeView' => $share->time_view,
            'expiresAt' => $share->expires_at?->toISOString(),
            'url' => url('/share').'?shared='.$share->uuid.'&state='.$share->state.'&timeView='.$share->time_view.'&view=true&notcopy=true',
        ], $status);
    }
}
