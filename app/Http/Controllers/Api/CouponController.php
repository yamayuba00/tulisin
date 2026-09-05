<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    /**
     * Daftar promo (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::latest()->get()->map(fn (Coupon $c) => [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'code' => $c->code,
            'type' => $c->type,
            'type_label' => $c->typeLabel(),
            'value' => $c->value,
            'max_uses' => $c->max_uses,
            'used_count' => $c->used_count,
            'expires_at' => $c->expires_at?->toISOString(),
            'is_active' => $c->is_active,
            'created_at' => $c->created_at?->toISOString(),
        ]);

        return response()->json([
            'total' => $coupons->count(),
            'coupons' => $coupons,
            'types' => Coupon::TYPES,
        ]);
    }

    /**
     * Buat promo baru (admin).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $coupon = Coupon::create([
            'code' => strtoupper(trim($data['code'])),
            'type' => $data['type'],
            'value' => (float) $data['value'],
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'used_count' => 0,
        ]);

        return response()->json([
            'message' => 'Promo berhasil dibuat.',
            'coupon' => $coupon,
        ], 201);
    }

    /**
     * Perbarui promo (admin).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $data = $this->validateData($request, $coupon->id);

        $coupon->update([
            'code' => strtoupper(trim($data['code'])),
            'type' => $data['type'],
            'value' => (float) $data['value'],
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Promo berhasil diperbarui.',
            'coupon' => $coupon,
        ]);
    }

    /**
     * Hapus promo (admin).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json(['message' => 'Promo dihapus.']);
    }

    /**
     * Validasi kode promo untuk nominal tertentu (dipakai halaman topup user).
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'integer', 'min:25000'],
        ]);

        $coupon = Coupon::where('code', strtoupper(trim($data['code'])))->first();

        if (! $coupon || ! $coupon->isUsable()) {
            return response()->json(['error' => 'Kode promo tidak valid atau sudah tidak bisa dipakai.'], 422);
        }

        $amount = (int) $data['amount'];
        $baseCredits = intdiv($amount, 500);
        $effect = $coupon->apply($amount, $baseCredits);

        return response()->json([
            'valid' => true,
            'coupon' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'type_label' => $coupon->typeLabel(),
                'value' => $coupon->value,
            ],
            'amount' => $amount,
            'payable' => $effect['payable'],
            'base_credits' => $effect['base_credits'],
            'bonus_credits' => $effect['bonus_credits'],
            'total_credits' => $effect['total_credits'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/'],
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(Coupon::TYPES))],
            'value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Kode promo unik (kecuali dirinya sendiri saat update).
        $exists = Coupon::where('code', strtoupper(trim($data['code'])))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            abort(422, 'Kode promo sudah dipakai.');
        }

        if (in_array($data['type'], ['bonus_percent', 'discount_percent'], true) && (float) $data['value'] > 100) {
            abort(422, 'Nilai persen maksimal 100.');
        }

        return $data;
    }
}
