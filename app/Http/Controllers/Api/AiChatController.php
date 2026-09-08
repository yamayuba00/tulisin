<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    /**
     * Daftar sesi chat AI milik satu project (untuk sidebar ala ChatGPT).
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $project = $this->ownedProject($request, $uuid);
        if (! $project) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $sessions = $project->aiChatSessions()
            ->with(['messages' => fn ($q) => $q->orderByDesc('created_at')->limit(1)])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (AiChatSession $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'preview' => $s->messages->first()?->content ?? '',
                'created_at' => $s->created_at?->toIso8601String(),
                'updated_at' => $s->updated_at?->toIso8601String(),
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Buat sesi chat baru (kosong, judul akan diisi otomatis dari pesan pertama).
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $project = $this->ownedProject($request, $uuid);
        if (! $project) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $session = $project->aiChatSessions()->create([
            'title' => trim((string) ($data['title'] ?? '')) ?: 'Chat baru',
        ]);

        return response()->json([
            'id' => $session->id,
            'title' => $session->title,
            'preview' => '',
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Ambil seluruh pesan pada satu sesi (history chat).
     */
    public function show(Request $request, string $uuid, AiChatSession $session): JsonResponse
    {
        $project = $this->ownedProject($request, $uuid);
        if (! $project || $session->project_id !== $project->id) {
            return response()->json(['error' => 'Chat tidak ditemukan.'], 404);
        }

        $messages = $session->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiChatMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'session' => ['id' => $session->id, 'title' => $session->title],
            'messages' => $messages,
        ]);
    }

    /**
     * Simpan satu pesan (user/assistant) ke sesi. Judul sesi otomatis diambil
     * dari pesan user pertama agar sidebar menampilkan topik yang jelas.
     */
    public function storeMessage(Request $request, string $uuid, AiChatSession $session): JsonResponse
    {
        $project = $this->ownedProject($request, $uuid);
        if (! $project || $session->project_id !== $project->id) {
            return response()->json(['error' => 'Chat tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'role' => ['required', 'string', 'in:user,assistant'],
            'content' => ['required', 'string'],
        ]);

        $message = $session->messages()->create([
            'role' => $data['role'],
            'content' => $data['content'],
        ]);

        // Auto-judul dari pesan user pertama.
        if ($data['role'] === 'user' && $session->title === 'Chat baru') {
            $title = trim(preg_replace('/\s+/', ' ', $data['content']));
            $session->update(['title' => mb_substr($title, 0, 60)]);
        } else {
            $session->touch();
        }

        return response()->json([
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Hapus satu sesi chat beserta seluruh riwayatnya.
     */
    public function destroy(Request $request, string $uuid, AiChatSession $session): JsonResponse
    {
        $project = $this->ownedProject($request, $uuid);
        if (! $project || $session->project_id !== $project->id) {
            return response()->json(['error' => 'Chat tidak ditemukan.'], 404);
        }

        $session->delete();

        return response()->json(['message' => 'Chat dihapus.']);
    }

    private function ownedProject(Request $request, string $uuid): ?Project
    {
        return Project::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();
    }
}
