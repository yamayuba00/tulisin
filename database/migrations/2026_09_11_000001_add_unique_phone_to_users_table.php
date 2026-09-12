<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });

        // Bersihkan nomor telepon duplikat sebelum membuat indeks unik:
        // pertahankan pengguna pertama (id terkecil), kosongkan sisanya.
        $duplicates = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('count(*) > 1')
            ->pluck('phone');

        foreach ($duplicates as $phone) {
            $keepId = DB::table('users')->where('phone', $phone)->orderBy('id')->value('id');

            DB::table('users')
                ->where('phone', $phone)
                ->where('id', '!=', $keepId)
                ->update(['phone' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
        });
    }
};
