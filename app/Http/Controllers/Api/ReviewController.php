<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Kirim review baru (masuk antrean moderasi admin).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['required', 'string', 'max:150'],
        ]);

        $review = Review::create([
            'user_id' => $request->user()->id,
            'rating' => (int) $data['rating'],
            'text' => trim((string) $data['text']),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Review terkirim dan menunggu moderasi admin.',
            'review' => $this->serialize($review),
        ], 201);
    }

    /**
     * Daftar review milik user yang login (untuk sidebar).
     */
    public function mine(Request $request): JsonResponse
    {
        $reviews = Review::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Review $r) => $this->serialize($r));

        return response()->json(['reviews' => $reviews]);
    }

    /**
     * Daftar review yang sudah dipublikasikan (publik).
     * Dipakai homepage (maks 9) dan halaman "semua review" (pagination 12).
     */
    public function published(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 9)));
        $paginator = Review::with('user:id,name,uuid')
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'reviews' => $paginator->getCollection()
                ->map(fn (Review $r) => $this->serialize($r))
                ->values(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    private function serialize(Review $r): array
    {
        $name = $r->user?->name ?? 'Pengguna';

        return [
            'id' => $r->uuid,
            'rating' => $r->rating,
            'text' => $r->text,
            'status' => $r->status,
            'name' => $name,
            'initial' => mb_substr($name, 0, 1),
            'created_at' => $r->created_at?->toISOString(),
            'published_at' => $r->published_at?->toISOString(),
        ];
    }
}
