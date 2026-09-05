<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuid;

    /** Tabel langganan per-user (terpisah dari tabel subscriptions organisasi B2B). */
    protected $table = 'user_subscriptions';

    /** Periode langganan aktif (hari). */
    public const PERIOD_DAYS = 30;

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'starts_at',
        'ends_at',
        'price',
        'payment_method',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminded_at' => 'datetime',
            'price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at?->isFuture();
    }
}
