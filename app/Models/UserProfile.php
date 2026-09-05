<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasUuid;

    protected $fillable = ['uuid', 'user_id', 'university', 'major', 'nim', 'degree'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
