<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Retrieval (RAG hybrid): cari chunk referensi paling relevan milik user
 * menggabungkan DUA sinyal pencarian tanpa mengirim seluruh daftar ke prompt:
 *
 * 1. Lexical (selalu jalan): full-text search Postgres (tsvector) dengan
 *    kombinasi ts_rank_cd phrase-aware + OR-query per kata, lengkap dengan
 *    stopword ID/EN.
 * 2. Vector/semantik (opsional): cosine distance pgvector (`embedding <=>
 *    query_vector`) memakai index HNSW. Otomatis aktif hanya bila kolom
 *    `embedding` sudah terisi sebagian untuk user ini; bila masih NULL semua
 *    (mis. DeepSeek belum menyediakan endpoint embeddings per Sept 2026),
 *    cabang ini diskip tanpa error.
 *
 * Penggabungan memakai Reciprocal Rank Fusion (RRF): tiap sinyal mengurutkan
 * kandidatnya, skor akhir = sum(1 / (60 + peringkat)). RRF tidak butuh
 * normalisasi skala skor, sehingga aman menggabungkan ts_rank (0..1+) dengan
 * jarak cosine (0..2).
 */
class RetrievalService
{
    /** Kata umum yang dibuang dari query agar pencarian fokus ke istilah bermakna. */
    private const STOPWORDS = [
        'yang', 'untuk', 'dengan', 'dari', 'pada', 'dalam', 'dan', 'atau', 'ke', 'di',
        'adalah', 'akan', 'ini', 'itu', 'sebagai', 'oleh', 'juga', 'dapat', 'bisa',
        'bagaimana', 'apa', 'apakah', 'kenapa', 'mengapa', 'buatkan', 'tuliskan', 'tolong',
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'are', 'with',
    ];

    /** Konstanta damping RRF (standar literatur: 60). */
    private const RRF_K = 60;

    /**
     * Cari top-K chunk paling relevan dengan $query milik user, dikelompokkan
     * per referensi (satu referensi maksimal muncul sekali dengan chunk
     * terbaiknya) agar hasil ke prompt tetap beragam sumber.
     *
     * @return array<int, array{ref_id: string, content: string, rank: float}>
     */
    public function searchTopReferences(int $userId, string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $lexical = $this->lexicalSearch($userId, $query, $limit * 4);
        $vector = $this->vectorSearch($userId, $query, $limit * 4);

        // Bila provider embeddings belum tersedia (vector kosong), kembalikan
        // hasil lexical apa adanya tanpa biaya RRF.
        if ($vector === []) {
            $rows = array_slice($lexical, 0, $limit);

            return array_map(fn ($r) => [
                'ref_id' => $r['ref_id'],
                'content' => $r['content'],
                'rank' => $r['rank'],
            ], $rows);
        }

        return $this->fuseRrf($lexical, $vector, $limit);
    }

    /**
     * Pencarian lexical (FTS). Mengembalikan urutan kandidat, tiap ref_id sekali
     * (chunk terbaik), lengkap dengan content agar bisa langsung dipakai.
     *
     * @return array<int, array{ref_id: string, content: string, rank: float}>
     */
    private function lexicalSearch(int $userId, string $query, int $limit): array
    {
        $orQuery = $this->buildOrQuery($query);

        $rows = DB::select(
            "select distinct on (ref_id) ref_id, content,
                    greatest(
                        ts_rank_cd(content_tsv, websearch_to_tsquery('simple', ?)),
                        ts_rank_cd(content_tsv, to_tsquery('simple', ?)) * 0.85
                    ) as rank
             from reference_chunks
             where user_id = ?
               and (
                    content_tsv @@ websearch_to_tsquery('simple', ?)
                    or content_tsv @@ to_tsquery('simple', ?)
               )
             order by ref_id, rank desc
             limit ?",
            [$query, $orQuery, $userId, $query, $orQuery, $limit],
        );

        usort($rows, fn ($a, $b) => $b->rank <=> $a->rank);

        return array_map(fn ($r) => [
            'ref_id' => (string) $r->ref_id,
            'content' => (string) $r->content,
            'rank' => (float) $r->rank,
        ], $rows);
    }

    /**
     * Pencarian vector (cosine distance, makin kecil makin mirip). Otomatis
     * mengembalikan [] bila: tidak ada chunk ber-embedding untuk user ini,
     * atau query tidak bisa di-embed (provider tidak mendukung).
     *
     * @return array<int, array{ref_id: string, content: string, distance: float}>
     */
    private function vectorSearch(int $userId, string $query, int $limit): array
    {
        $hasVectors = (bool) DB::selectOne(
            'select 1 from reference_chunks where user_id = ? and embedding is not null limit 1',
            [$userId],
        );

        if (! $hasVectors) {
            return [];
        }

        $vector = app(EmbeddingService::class)->embed($query);
        if ($vector === null || $vector === []) {
            return [];
        }

        $literal = '['.implode(',', array_map('floatval', $vector)).']';

        $rows = DB::select(
            'select distinct on (ref_id) ref_id, content,
                    (embedding <=> ?::vector) as distance
             from reference_chunks
             where user_id = ? and embedding is not null
             order by ref_id, distance asc
             limit ?',
            [$literal, $userId, $limit],
        );

        usort($rows, fn ($a, $b) => $a->distance <=> $b->distance);

        return array_map(fn ($r) => [
            'ref_id' => (string) $r->ref_id,
            'content' => (string) $r->content,
            'distance' => (float) $r->distance,
        ], $rows);
    }

    /**
     * Gabungkan dua daftar peringkat (lexical + vector) dengan Reciprocal Rank
     * Fusion, dikelompokkan per ref_id (entri pertama = peringkat terbaik).
     *
     * @param  array<int, array{ref_id: string, content: string, rank: float}>  $lexical
     * @param  array<int, array{ref_id: string, content: string, distance: float}>  $vector
     * @return array<int, array{ref_id: string, content: string, rank: float}>
     */
    private function fuseRrf(array $lexical, array $vector, int $limit): array
    {
        $scores = [];
        $contents = [];

        foreach ([$lexical, $vector] as $list) {
            foreach (array_values($list) as $pos => $item) {
                $id = $item['ref_id'];
                if (! isset($contents[$id])) {
                    $contents[$id] = $item['content'];
                }
                $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / (self::RRF_K + $pos + 1);
            }
        }

        arsort($scores);
        $out = [];
        foreach (array_slice($scores, 0, $limit, true) as $id => $score) {
            $out[] = ['ref_id' => $id, 'content' => $contents[$id], 'rank' => $score];
        }

        return $out;
    }

    /**
     * Susun tsquery OR (`kata1 | kata2 | ...`) dari kata bermakna pada query
     * bebas, sebagai jaring pengaman saat frasa persis tidak match tapi
     * kata kuncinya ada (urutan beda, sinonim parsial, dll).
     */
    private function buildOrQuery(string $query): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [];

        $terms = array_values(array_unique(array_filter(
            $words,
            fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOPWORDS, true),
        )));

        if ($terms === []) {
            // Tidak ada kata bermakna: pakai query asli literal agar to_tsquery tidak error.
            $terms = array_values(array_filter($words, fn ($w) => $w !== ''));
        }

        if ($terms === []) {
            return 'x_no_match_x';
        }

        // Token sudah difilter hanya huruf/angka, aman untuk sintaks tsquery prefix (:*).
        return implode(' | ', array_map(fn ($w) => $w.':*', $terms));
    }
}
