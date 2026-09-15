<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ShowcaseImage extends Model
{
    use HasUuid;

    protected $table = 'showcase_images';

    protected $fillable = ['uuid', 'mime', 'path', 'alt', 'caption', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
