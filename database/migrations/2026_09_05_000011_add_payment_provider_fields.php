<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('fee', 15, 2)->default(0)->after('amount');
            $table->string('payment_url')->nullable()->after('provider_ref');
            $table->json('payload')->nullable()->after('payment_url');
            $table->timestamp('expires_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['fee', 'payment_url', 'payload', 'expires_at']);
        });
    }
};
