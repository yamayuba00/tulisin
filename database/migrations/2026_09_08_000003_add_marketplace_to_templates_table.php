<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Harga (koin) yang dibayar pengguna lain untuk memakai template ini.
            $table->unsignedInteger('price')->default(8)->after('blocks');
            // Bagian (koin) yang diterima pembuat template dari tiap pembelian.
            $table->unsignedInteger('creator_share')->default(2)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['creator_share', 'price']);
        });
    }
};
