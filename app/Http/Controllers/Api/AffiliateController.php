<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    /**
     * Kode referral + statistik affiliate milik pengguna saat ini.
     * Kode referral dibuat otomatis saat pertama kali diakses.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $code = ReferralCode::firstOrCreate(
            ['user_id' => $user->id],
            ['code' => $this->generateUniqueCode(), 'is_active' => true],
        );

        $referrals = Referral::with('referredUser')
            ->where('referrer_id', $user->id)
            ->latest()
            ->get();

        $earned = (int) CreditTransaction::where('user_id', $user->id)
            ->where('reason', 'affiliate_referral')
            ->sum('amount');

        return response()->json([
            'code' => $code->code,
            'is_active' => $code->is_active,
            'credit_per_referral' => Referral::CREDIT_PER_REFERRAL,
            'total_referred' => $referrals->count(),
            'earned_credits' => $earned,
            'referrals' => $referrals->map(fn (Referral $r) => [
                'id' => $r->uuid,
                'name' => $r->referredUser?->name,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Perbarui kode referral milik pengguna (kustom).
     * Normalisasi: trim + huruf besar; hanya huruf & angka (tanpa spasi).
     */
    public function updateCode(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code', '')));

        if ($code === '') {
            return response()->json(['error' => 'Kode referral wajib diisi.'], 422);
        }
        if (strlen($code) < 4) {
            return response()->json(['error' => 'Kode referral minimal 4 karakter.'], 422);
        }
        if (strlen($code) > 40) {
            return response()->json(['error' => 'Kode referral maksimal 40 karakter.'], 422);
        }
        if (! preg_match('/^[A-Z0-9]+$/', $code)) {
            return response()->json(['error' => 'Kode referral hanya boleh huruf dan angka (tanpa spasi).'], 422);
        }

        $user = $request->user();

        if (ReferralCode::where('code', $code)->where('user_id', '!=', $user->id)->exists()) {
            return response()->json(['error' => 'Kode referral sudah dipakai pengguna lain.'], 422);
        }

        $referralCode = ReferralCode::firstOrCreate(
            ['user_id' => $user->id],
            ['code' => $code, 'is_active' => true],
        );
        $referralCode->update(['code' => $code]);

        return response()->json([
            'message' => 'Kode referral berhasil disimpan.',
            'code' => $referralCode->code,
        ]);
    }

    /**
     * Generate kode referral unik (huruf besar alfanumerik).
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (ReferralCode::where('code', $code)->exists());

        return $code;
    }
}
