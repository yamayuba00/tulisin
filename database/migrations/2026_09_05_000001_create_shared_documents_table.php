<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->longText('payload'); // JSON snapshot dokumen (settings + blocks)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_documents');
    }
};
