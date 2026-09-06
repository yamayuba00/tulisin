<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('title')->nullable();
            $table->unsignedInteger('recipients_total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->string('status')->default('queued');
            $table->string('batch_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_broadcasts');
    }
};
