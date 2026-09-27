<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            // Format dokumen yang dipilih saat chat dibuat. Setelah diset,
            // format ini tidak boleh diubah agar "otak" percakapan konsisten.
            $table->string('format', 30)->nullable()->after('title');
            // Gaya penyisipan yang dipilih saat chat dibuat: after | replace.
            $table->string('insert_mode', 20)->default('replace')->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->dropColumn(['format', 'insert_mode']);
        });
    }
};
