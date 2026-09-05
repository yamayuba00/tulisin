<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRevision extends Model
{
    protected $fillable = [
        'project_id',
        'version',
        'payload',
        'cause',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'version' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
