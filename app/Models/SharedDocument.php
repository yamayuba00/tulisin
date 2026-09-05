<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedDocument extends Model
{
    use HasUuid;

    protected $table = 'shared_documents';

    protected $fillable = ['uuid', 'user_id', 'name', 'payload', 'state', 'time_view', 'expires_at', 'project_uuid'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'time_view' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
