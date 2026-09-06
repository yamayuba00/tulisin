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
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
