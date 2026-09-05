<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'category',
        'description',
        'format',
        'orientation',
        'status',
        'is_public',
        'published_at',
        'payload',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProjectRevision::class);
    }

    public function aiResults(): HasMany
    {
        return $this->hasMany(ProjectAiResult::class);
    }
}
