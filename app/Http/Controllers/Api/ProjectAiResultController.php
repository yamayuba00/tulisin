<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAiResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAiResultController extends Controller
{
    /**
     * Daftar riwayat hasil AI (turnitin/plagiarism) milik satu project.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $project = Project::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $project) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $results = $project->aiResults()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'score' => $r->score,
                'matches' => $r->matches ?? [],
                'created_at' => $r->created_at?->toISOString(),
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Simpan hasil scan AI agar bisa dibuka kembali (laporan & pembelajaran).
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $project = Project::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $project) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:turnitin,plagiarism'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'matches' => ['present', 'array'],
        ]);

        $result = $project->aiResults()->create([
            'type' => $data['type'],
            'score' => $data['score'],
            'matches' => $data['matches'],
        ]);

        return response()->json([
            'id' => $result->id,
            'type' => $result->type,
            'score' => $result->score,
            'matches' => $result->matches,
            'created_at' => $result->created_at?->toISOString(),
        ], 201);
    }

    /**
     * Hapus satu entri riwayat AI.
     */
    public function destroy(Request $request, string $uuid, ProjectAiResult $result): JsonResponse
    {
        $project = Project::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $project || $result->project_id !== $project->id) {
            return response()->json(['error' => 'Data tidak ditemukan.'], 404);
        }

        $result->delete();

        return response()->json(['message' => 'Riwayat dihapus.']);
    }
}
