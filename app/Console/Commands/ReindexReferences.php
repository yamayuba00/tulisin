<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReferenceIndexer;
use Illuminate\Console\Command;

/**
 * Backfill: indeks ulang seluruh referensi Workspace yang sudah ada (dibuat
 * sebelum RAG Fase 0 aktif) menjadi chunks + tsvector di `reference_chunks`.
 *
 * Jalankan sekali setelah migration `reference_chunks` diterapkan:
 *   php artisan references:reindex
 */
class ReindexReferences extends Command
{
    protected $signature = 'references:reindex {--user= : Hanya reindex satu user_id}';

    protected $description = 'Indeks ulang referensi Workspace menjadi chunks (RAG Fase 0)';

    public function handle(ReferenceIndexer $indexer): int
    {
        $userId = $this->option('user');

        $users = $userId
            ? User::query()->where('id', $userId)->get()
            : User::query()->whereHas('workspaceReferences')->get();

        if ($users->isEmpty()) {
            $this->info('Tidak ada user dengan referensi Workspace.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $total = 0;
        foreach ($users as $user) {
            $total += $indexer->reindexUser($user->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Selesai. {$total} referensi diindeks dari {$users->count()} user.");

        return self::SUCCESS;
    }
}
