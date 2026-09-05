<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditSubmissionController extends Controller
{
    /**
     * Riwayat pengajuan koin milik pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $submissions = DB::table('credit_submissions')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'url' => $s->url,
                'notes' => $s->notes,
                'views' => (int) $s->views,
                'status' => $s->status,
                'credits_awarded' => (int) $s->credits_awarded,
                'review_note' => $s->review_note,
                'created_at' => $s->created_at,
            ]);

        return response()->json(['submissions' => $submissions]);
    }

    /**
     * Kirim tautan konten untuk diverifikasi admin (hanya sekali per pengguna).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $alreadySubmitted = DB::table('credit_submissions')
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadySubmitted) {
            return response()->json([
                'error' => 'Anda hanya dapat mengirim tautan satu kali.',
            ], 422);
        }

        $data = $request->validate([
            'url' => ['required', 'url'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'views' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::table('credit_submissions')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'url' => $data['url'],
            'notes' => $data['notes'] ?? null,
            'views' => (int) ($data['views'] ?? 0),
            'status' => 'pending',
            'credits_awarded' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Kiriman berhasil dikirim dan menunggu verifikasi admin.',
        ], 201);
    }
}
