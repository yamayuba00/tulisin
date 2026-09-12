<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_payouts', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('method');
            $table->string('account_number', 40)->nullable()->after('bank_name');
            $table->string('account_name')->nullable()->after('account_number');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->foreignId('referral_id')->nullable()->after('payment_id')->constrained('referrals')->nullOnDelete();
            $table->unsignedInteger('discount_amount')->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_payouts', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_number', 'account_name']);
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_id');
            $table->dropColumn('discount_amount');
        });
    }
};
