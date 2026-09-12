<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePayout extends Model
{
    use HasUuid;

    protected $table = 'affiliate_payouts';

    protected $fillable = [
        'uuid',
        'affiliate_id',
        'amount',
        'method',
        'account_detail',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }
}
