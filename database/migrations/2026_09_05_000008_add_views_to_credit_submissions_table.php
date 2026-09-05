<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_submissions', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('credit_submissions', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
