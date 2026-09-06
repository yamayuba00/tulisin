<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BlastImage extends Model
{
    use HasUuid;

    protected $table = 'blast_images';

    protected $fillable = ['uuid', 'mime', 'path'];
}
