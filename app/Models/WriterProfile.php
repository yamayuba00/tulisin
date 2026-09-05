<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriterProfile extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'user_id', 'agency_name', 'bio', 'specialties',
        'rating_avg', 'completed_orders', 'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'rating_avg' => 'float',
            'is_verified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
