<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'category',
        'description',
        'format',
        'font',
        'blocks',
        'price',
        'creator_share',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'price' => 'integer',
            'creator_share' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
