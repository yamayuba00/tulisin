<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasUuid;

    public const TYPES = [
        'bonus_percent' => 'Bonus Koin (%)',
        'bonus_fixed' => 'Bonus Koin (koin)',
        'discount_percent' => 'Diskon (%)',
        'discount_fixed' => 'Diskon (Rp)',
    ];

    protected $fillable = [
        'uuid',
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Hitung efek promo untuk nominal pembayaran & koin dasar tertentu.
     *
     * @return array{payable:int, base_credits:int, bonus_credits:int, total_credits:int}
     */
    public function apply(int $amount, int $baseCredits): array
    {
        $payable = $amount;
        $bonus = 0;

        switch ($this->type) {
            case 'bonus_percent':
                $bonus = (int) round($baseCredits * ((float) $this->value / 100));
                break;

            case 'bonus_fixed':
                $bonus = (int) round((float) $this->value);
                break;

            case 'discount_percent':
                $payable = (int) round($amount * (1 - ((float) $this->value / 100)));
                break;

            case 'discount_fixed':
                $payable = max(0, $amount - (int) round((float) $this->value));
                break;
        }

        return [
            'payable' => $payable,
            'base_credits' => $baseCredits,
            'bonus_credits' => $bonus,
            'total_credits' => $baseCredits + $bonus,
        ];
    }
}
