<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Font extends Model
{
    use HasUuid;

    protected $table = 'fonts';

    protected $fillable = ['uuid', 'user_id', 'family', 'format', 'mime', 'path'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
