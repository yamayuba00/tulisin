<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Wallet extends Model
{
    use HasUuid;

    protected $fillable = ['uuid', 'user_id', 'balance', 'on_hold'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'on_hold' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Tambah saldo kredit.
     */
    public function credit(int $amount, string $reason, ?string $refType = null, ?int $refId = null): int
    {
        return DB::transaction(function () use ($amount, $reason, $refType, $refId) {
            $this->balance += $amount;
            $this->save();

            $this->transactions()->create([
                'user_id' => $this->user_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'reason' => $reason,
                'reference_type' => $refType,
                'reference_id' => $refId,
            ]);

            return $this->balance;
        });
    }

    /**
     * Kurangi saldo kredit. Lempar exception bila saldo tidak cukup.
     */
    public function debit(int $amount, string $reason, ?string $refType = null, ?int $refId = null): int
    {
        return DB::transaction(function () use ($amount, $reason, $refType, $refId) {
            if ($this->balance < $amount) {
                throw new RuntimeException('Saldo koin tidak mencukupi.');
            }

            $this->balance -= $amount;
            $this->save();

            $this->transactions()->create([
                'user_id' => $this->user_id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'reason' => $reason,
                'reference_type' => $refType,
                'reference_id' => $refId,
            ]);

            return $this->balance;
        });
    }
}
