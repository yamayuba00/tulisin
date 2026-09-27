<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector sudah terpasang v0.8.2 di server ini (PostgreSQL 16.13).
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('reference_chunks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Cocok dengan workspace_references.ref_id (string client id, mis. ws_xxx)
            $table->string('ref_id', 64);
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            // Full-text search (tanpa embedding tetap jalan).
            $table->tsvector('content_tsv')->nullable();
            // Vector untuk embedding nanti. Nullable agar Fase 0 (tsvector only) tetap jalan.
            // Dimensi 1536 = OpenAI text-embedding-3-small (ganti bila provider lain).
            $table->vector('embedding', 1536)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ref_id', 'chunk_index']);
            $table->index(['user_id', 'ref_id']);

            // Index full-text (GIN) dan vector (HNSW, pgvector 0.8.2 mendukung index kosong).
            $table->index('content_tsv', 'reference_chunks_content_tsv_idx')->algorithm('gin');
            $table->vectorIndex('embedding', 'reference_chunks_embedding_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_chunks');
        // Jangan drop extension vector karena mungkin dipakai tabel lain nantinya.
    }
};
