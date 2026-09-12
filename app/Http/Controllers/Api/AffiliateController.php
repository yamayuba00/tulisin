<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $commissionBalance = (float) AffiliateCommission::where('affiliate_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        $totalWithdrawn = (float) AffiliatePayout::where('affiliate_id', $user->id)
            ->whereIn('status', ['paid', 'pending'])
            ->sum('amount');

        return response()->json([
            'code' => $code->code,
            'is_active' => $code->is_active,
            'total_referred' => $referrals->count(),
            'commission_balance' => $commissionBalance,
            'total_withdrawn' => $totalWithdrawn,
            'conversion_rate' => (int) config('affiliate.conversion_rate', 250),
            'min_withdrawal' => (int) config('affiliate.min_withdrawal', 50000),
            'commission_per_referral' => (int) config('affiliate.commission_per_referral', 4000),
            'referral_discount' => (int) config('affiliate.referral_discount', 10000),
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
     * Tarik komisi: tukar ke koin (langsung masuk wallet) atau ke rekening bank.
     * Minimal penarikan mengikuti config affiliate.min_withdrawal.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversionRate = max(1, (int) config('affiliate.conversion_rate', 250));
        $minWithdrawal = (int) config('affiliate.min_withdrawal', 50000);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:coins,bank'],
            'bank_name' => ['required_if:type,bank', 'nullable', 'string', 'max:100'],
            'account_number' => ['required_if:type,bank', 'nullable', 'string', 'max:40'],
            'account_name' => ['required_if:type,bank', 'nullable', 'string', 'max:100'],
        ]);

        $balance = (float) AffiliateCommission::where('affiliate_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        if ($balance < $minWithdrawal) {
            return response()->json([
                'error' => 'Saldo komisi belum mencapai minimal penarikan Rp ' . number_format($minWithdrawal, 0, ',', '.') . '.',
            ], 422);
        }

        $coins = (int) floor($balance / $conversionRate);

        DB::transaction(function () use ($user, $data, $balance, $coins) {
            if ($data['type'] === 'coins') {
                $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
                $wallet->credit($coins, 'affiliate_withdraw');

                AffiliatePayout::create([
                    'affiliate_id' => $user->id,
                    'amount' => $balance,
                    'method' => 'coins',
                    'account_detail' => $coins . ' koin',
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            } else {
                AffiliatePayout::create([
                    'affiliate_id' => $user->id,
                    'amount' => $balance,
                    'method' => 'bank',
                    'bank_name' => $data['bank_name'] ?? null,
                    'account_number' => $data['account_number'] ?? null,
                    'account_name' => $data['account_name'] ?? null,
                    'account_detail' => trim(($data['bank_name'] ?? '') . ' ' . ($data['account_number'] ?? '') . ' a.n. ' . ($data['account_name'] ?? '')),
                    'status' => 'pending',
                ]);
            }

            AffiliateCommission::where('affiliate_id', $user->id)
                ->where('status', 'approved')
                ->update(['status' => 'withdrawn']);
        });

        return response()->json([
            'message' => $data['type'] === 'coins'
                ? 'Komisi berhasil ditukar menjadi ' . $coins . ' koin.'
                : 'Permintaan penarikan ke rekening bank telah dikirim.',
        ]);
    }

    /**
     * Riwayat penarikan komisi milik pengguna saat ini.
     */
    public function payouts(Request $request): JsonResponse
    {
        $user = $request->user();

        $payouts = AffiliatePayout::where('affiliate_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (AffiliatePayout $p) => [
                'id' => $p->uuid,
                'amount' => $p->amount,
                'method' => $p->method,
                'bank_name' => $p->bank_name,
                'account_number' => $p->account_number,
                'status' => $p->status,
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return response()->json(['payouts' => $payouts]);
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
