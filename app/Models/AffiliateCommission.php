<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    use HasUuid;

    protected $table = 'affiliate_commissions';

    protected $fillable = [
        'uuid',
        'affiliate_id',
        'referral_id',
        'reference_type',
        'reference_id',
        'amount',
        'rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'rate' => 'float',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
