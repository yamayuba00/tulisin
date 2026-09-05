<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_documents', function (Blueprint $table) {
            $table->string('project_uuid', 36)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('shared_documents', function (Blueprint $table) {
            $table->dropColumn('project_uuid');
        });
    }
};
