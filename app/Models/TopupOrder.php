<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupOrder extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'payment_id',
        'credit_package_id',
        'credits',
        'coupon_code',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'amount' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
