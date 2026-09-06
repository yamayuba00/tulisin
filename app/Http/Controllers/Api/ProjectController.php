<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Daftar dokumen milik pengguna yang sedang login (untuk halaman Projects).
     */
    public function index(Request $request): JsonResponse
    {
        $projects = Project::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->uuid,
                'name' => $p->title,
                'category' => $p->category,
                'lastEdited' => $p->updated_at ? (int) $p->updated_at->valueOf() : null,
                'blocks' => data_get($p->payload, 'blocks', []),
            ]);

        return response()->json(['projects' => $projects]);
    }

    /**
     * Daftar project publik (untuk halaman Lists Project — jelajah project
     * pengguna lain). Hanya project yang sudah dipublikasikan.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->where('is_public', true)
            ->where('status', 'published')
            ->where('user_id', '!=', $request->user()->id)
            ->with('user:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->uuid,
                'title' => $p->title,
                'author' => $p->user?->name ?? 'Anonim',
                'category' => $p->category,
                'description' => $p->description,
                'updatedAt' => ($p->published_at ?? $p->updated_at)?->toISOString(),
            ]);

        return response()->json(['projects' => $projects]);
    }

    /**
     * Ambil dokumen milik pengguna yang sedang login.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $project = Project::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $project) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        return response()->json([
            'uuid' => $project->uuid,
            'name' => $project->title,
            'version' => (int) $project->version,
            'payload' => $project->payload ?? [],
        ]);
    }

    /**
     * Simpan seluruh isi builder (settings + blok) sebagai JSONB di PostgreSQL.
     * Memakai optimistic locking via kolom `version` agar dua tab/klien tidak
     * saling menimpa perubahan.
     */
    public function save(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'array'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $payload = $data['payload'];
        $clientVersion = (int) $data['version'];

        $project = Project::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        $attributes = [
            'title' => $payload['name'] ?? 'Proyek Tanpa Judul',
            'category' => $payload['category'] ?? 'Lainnya',
            'format' => $payload['format'] ?? 'A4',
            'orientation' => $payload['orientation'] ?? 'portrait',
            'payload' => $payload,
        ];

        if ($project) {
            // Versi client tidak sama dengan DB → ada perubahan di tempat lain.
            if ($clientVersion !== (int) $project->version) {
                return response()->json([
                    'error' => 'Dokumen sudah diperbarui di tempat lain. Muat ulang data terbaru.',
                    'version' => (int) $project->version,
                    'name' => $project->title,
                    'payload' => $project->payload ?? [],
                ], 409);
            }

            // Simpan snapshot versi sebelumnya agar semua perubahan tercatat.
            $this->snapshotRevision($project, 'autosave');

            $project->update([...$attributes, 'version' => (int) $project->version + 1]);
        } else {
            $project = Project::create([
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                ...$attributes,
                'version' => 1,
            ]);
        }

        return response()->json([
            'uuid' => $project->uuid,
            'name' => $project->title,
            'version' => (int) $project->version,
            'saved_at' => $project->updated_at?->toISOString(),
        ]);
    }

    /**
     * Simpan payload versi sebelumnya sebagai snapshot riwayat (skip bila tak berubah).
     */
    private function snapshotRevision(Project $project, string $cause): void
    {
        if (! $project->payload) {
            return;
        }

        $last = $project->revisions()->latest('id')->first();
        if ($last && $last->payload === $project->payload) {
            return;
        }

        $project->revisions()->create([
            'version' => (int) $project->version,
            'payload' => $project->payload,
            'cause' => $cause,
        ]);
    }

    /**
     * Hapus dokumen milik pengguna (termasuk saat project dihapus dari daftar).
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $project = Project::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $project) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $project->delete();

        return response()->json(['message' => 'Dokumen dihapus.']);
    }
}
