<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('user_id')->constrained('payments')->nullOnDelete();
            $table->timestamp('starts_at')->nullable()->change();
            $table->timestamp('ends_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
            $table->timestamp('starts_at')->nullable(false)->change();
            $table->timestamp('ends_at')->nullable(false)->change();
        });
    }
};
