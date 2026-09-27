<?php

namespace App\Console\Commands;

use App\Services\RetrievalService;
use Illuminate\Console\Command;

/**
 * Tes retrieval RAG langsung dari terminal tanpa harus generate di canvas.
 *
 * Contoh:
 *   php artisan rag:search 2 "sistem pakar ISPA"
 *   php artisan rag:search 2 "robot pelayan" --limit=8
 */
class RagSearch extends Command
{
    protected $signature = 'rag:search {user_id : ID user pemilik referensi} {query : Kata kunci / kalimat topik} {--limit=8 : Jumlah referensi maksimal}';

    protected $description = 'Cari referensi paling relevan lewat RAG (lexical + vector bila aktif)';

    public function handle(RetrievalService $retrieval): int
    {
        $userId = (int) $this->argument('user_id');
        $query = trim((string) $this->argument('query'));
        $limit = max(1, (int) $this->option('limit'));

        if ($query === '') {
            $this->error('Query tidak boleh kosong.');

            return self::FAILURE;
        }

        $hits = $retrieval->searchTopReferences($userId, $query, $limit);

        if ($hits === []) {
            $this->warn('Tidak ada hasil. Pastikan referensi sudah diindeks (php artisan references:reindex) dan topiknya ada di referensi user tsb.');

            return self::SUCCESS;
        }

        $this->info("Hasil untuk user #{$userId}: \"{$query}\"");
        $rows = [];
        foreach ($hits as $i => $hit) {
            $rows[] = [
                ($i + 1).'.'.($hit['ref_id']),
                number_format($hit['rank'], 4),
                mb_substr(preg_replace('/\s+/u', ' ', $hit['content']), 0, 90).'...',
            ];
        }

        $this->table(['ref_id', 'skor', 'cuplikan'], $rows);

        return self::SUCCESS;
    }
}
