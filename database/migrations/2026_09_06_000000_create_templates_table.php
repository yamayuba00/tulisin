<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40)->default('Custom');
            $table->text('description')->nullable();
            $table->string('format', 20)->default('A4');
            $table->string('font', 100)->default('Times New Roman');
            $table->json('blocks');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
