<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailBroadcast extends Model
{
    protected $fillable = [
        'subject',
        'title',
        'recipients_total',
        'processed',
        'failed',
        'status',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'recipients_total' => 'integer',
            'processed' => 'integer',
            'failed' => 'integer',
        ];
    }
}
