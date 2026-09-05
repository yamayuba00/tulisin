<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Isi kolom `uuid` untuk user lama yang masih NULL, supaya bisa dipakai
     * sebagai nama folder di object storage (workspace/{uuid}, file_manager/{uuid}).
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('uuid')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    /**
     * Tidak ada rollback untuk backfill data.
     */
    public function down(): void
    {
        //
    }
};
