<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Mengisi kolom `uuid` secara otomatis saat record dibuat.
 * `uuid` dipakai sebagai identitas publik (URL/API), sedangkan `id` tetap untuk JOIN internal.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }
}
