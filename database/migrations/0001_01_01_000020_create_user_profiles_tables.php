<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('university')->nullable();
            $table->string('major')->nullable();
            $table->string('nim', 40)->nullable();
            $table->string('degree', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('writer_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('agency_name')->nullable();
            $table->text('bio')->nullable();
            $table->json('specialties')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('advertiser_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('tax_id', 64)->nullable();
            $table->string('billing_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertiser_profiles');
        Schema::dropIfExists('writer_profiles');
        Schema::dropIfExists('user_profiles');
    }
};
