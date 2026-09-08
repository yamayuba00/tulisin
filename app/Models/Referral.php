<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasUuid;

    /** Kredit bawaan yang diberikan ke perujuk (bisa diubah admin lewat Pengaturan Koin). */
    public const CREDIT_PER_REFERRAL = 10;

    /**
     * Kredit per referral yang aktif, dibaca dari Pengaturan Koin admin
     * (tabel settings key `credit_pricing`), fallback ke config/default.
     */
    public static function creditPerReferral(): int
    {
        $default = (int) config('credits.pricing.affiliate_referral', self::CREDIT_PER_REFERRAL);
        $setting = Setting::where('key', 'credit_pricing')->first();
        $stored = $setting ? $setting->value : null;

        if (is_array($stored) && isset($stored['affiliate_referral'])) {
            return (int) $stored['affiliate_referral'];
        }

        return $default;
    }

    protected $fillable = [
        'uuid',
        'referrer_id',
        'referred_user_id',
        'referral_code_id',
        'status',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }
}
