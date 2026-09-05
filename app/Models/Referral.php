<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasUuid;

    /** Kredit yang diberikan ke perujuk untuk tiap pengguna baru yang mendaftar. */
    public const CREDIT_PER_REFERRAL = 20;

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
