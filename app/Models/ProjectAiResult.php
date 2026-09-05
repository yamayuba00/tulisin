<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAiResult extends Model
{
    protected $fillable = [
        'project_id',
        'type',
        'score',
        'matches',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'matches' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
