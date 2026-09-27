<?php

namespace App\Services;

use App\Models\WorkspaceReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Indexing referensi Workspace menjadi chunks untuk retrieval (RAG).
 *
 * Sumber teks: field CSL-JSON di kolom `data` (title, abstract, keywords,
 * authors, container-title). Teks dipecah jadi chunks ~800 karakter dengan
 * overlap 100 karakter, lalu kolom content_tsv diisi via to_tsvector.
 *
 * Embedding (kolom `embedding` vector(1536)) diisi bila provider mendukung
 * endpoint /embeddings (lihat EmbeddingService). DeepSeek belum menyediakannya
 * per Sept 2026 (terverifikasi 404), jadi kolom dibiarkan NULL dan retrieval
 * jalan lexical-only tanpa error; otomatis aktif saat provider siap.
 */
class ReferenceIndexer
{
    /** Panjang target tiap chunk (karakter). */
    private const CHUNK_SIZE = 800;

    /** Overlap antar chunk (karakter) agar kalimat terpotong tetap ada konteks. */
    private const CHUNK_OVERLAP = 100;

    /** Batas total karakter teks yang diindeks per referensi. */
    private const MAX_SOURCE_CHARS = 12000;

    /**
     * Indeks (rebuild) chunks untuk satu referensi user.
     */
    public function indexReference(int $userId, string $refId, array $data): void
    {
        $text = $this->sourceText($data);

        // Hapus chunks lama terlebih dahulu (idempoten, aman dipanggil ulang).
        DB::table('reference_chunks')
            ->where('user_id', $userId)
            ->where('ref_id', $refId)
            ->delete();

        if (trim($text) === '') {
            return;
        }

        $chunks = $this->chunk($text);
        if ($chunks === []) {
            return;
        }

        // Embedding dibuat sekaligus per batch (satu request untuk semua chunk
        // referensi ini). Bila provider tidak mendukung (DeepSeek saat ini),
        // hasilnya null dan kolom embedding dibiarkan NULL (lexical-only tetap jalan).
        $vectors = app(EmbeddingService::class)->embedBatch($chunks);

        foreach (array_values($chunks) as $i => $content) {
            $vector = $vectors[$i] ?? null;
            $vectorLiteral = $vector !== null ? $this->vectorLiteral($vector) : null;

            DB::insert(
                'insert into reference_chunks (uuid, user_id, ref_id, chunk_index, content, content_tsv, embedding, created_at, updated_at)
                 values (?, ?, ?, ?, ?, to_tsvector(\'simple\', ?), ?, ?, ?)',
                [
                    (string) Str::uuid(), $userId, $refId, $i, $content, $content,
                    $vectorLiteral, now(), now(),
                ],
            );
        }
    }

    /**
     * Indeks semua referensi milik user (untuk command reindex).
     */
    public function reindexUser(int $userId): int
    {
        $refs = WorkspaceReference::query()->where('user_id', $userId)->get();
        $count = 0;

        foreach ($refs as $ref) {
            $this->indexReference($userId, $ref->ref_id, is_array($ref->data) ? $ref->data : []);
            $count++;
        }

        return $count;
    }

    /**
     * Susun teks sumber dari metadata CSL-JSON.
     *
     * @param  array<string, mixed>  $data
     */
    private function sourceText(array $data): string
    {
        $parts = [];

        $title = trim((string) ($data['title'] ?? ''));
        if ($title !== '') {
            $parts[] = 'Judul: '.$title;
        }

        $authors = $data['author'] ?? [];
        if (is_array($authors) && $authors !== []) {
            $names = [];
            foreach ($authors as $a) {
                $name = trim((string) ($a['family'] ?? '').' '.(string) ($a['given'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            if ($names !== []) {
                $parts[] = 'Penulis: '.implode(', ', $names);
            }
        }

        $journal = trim((string) ($data['container-title'] ?? $data['journal'] ?? ''));
        if ($journal !== '') {
            $parts[] = 'Sumber: '.$journal;
        }

        $year = (string) ($data['year'] ?? ($data['issued']['date-parts'][0][0] ?? ''));
        if ($year !== '') {
            $parts[] = 'Tahun: '.$year;
        }

        $keywords = $data['keywords'] ?? ($data['_keywords'] ?? []);
        if (is_array($keywords) && $keywords !== []) {
            $parts[] = 'Kata kunci: '.implode(', ', array_map('strval', $keywords));
        }

        $abstract = trim((string) ($data['abstract'] ?? $data['_abstract'] ?? ''));
        if ($abstract !== '') {
            $parts[] = 'Abstrak: '.$abstract;
        }

        $snippet = trim((string) ($data['_snippet'] ?? ''));
        if ($snippet !== '') {
            $parts[] = 'Ringkasan: '.$snippet;
        }

        return mb_substr(implode("\n\n", $parts), 0, self::MAX_SOURCE_CHARS);
    }

    /**
     * Ubah array float menjadi literal vector Postgres ('[0.1,0.2,...]') yang
     * diterima oleh kolom vector pgvector. Dimensi yang salah di-log agar
     * mudah dilacak, tapi tidak menggagalkan indexing (lexical tetap jalan).
     *
     * @param  array<int, float>  $vector
     */
    private function vectorLiteral(array $vector): string
    {
        $expected = (int) config('services.embedding.dimensions', 1536);
        if ($expected > 0 && count($vector) !== $expected) {
            Log::warning('ReferenceIndexer: dimensi embedding tidak sesuai kolom', [
                'got' => count($vector),
                'expected' => $expected,
            ]);
        }

        return '['.implode(',', array_map('floatval', $vector)).']';
    }

    /**
     * Pecah teks jadi chunks dengan overlap, memotong di batas kalimat bila bisa.
     *
     * @return array<int, string>
     */
    private function chunk(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $len = mb_strlen($text);

        if ($len <= self::CHUNK_SIZE) {
            return $text === '' ? [] : [$text];
        }

        $chunks = [];
        $start = 0;

        while ($start < $len) {
            $end = min($start + self::CHUNK_SIZE, $len);
            $piece = mb_substr($text, $start, $end - $start);

            // Coba potong di titik/akhir kalimat terakhir agar chunk bersih.
            if ($end < $len) {
                $lastDot = mb_strrpos($piece, '.');
                if ($lastDot !== false && $lastDot > self::CHUNK_SIZE * 0.5) {
                    $piece = mb_substr($piece, 0, $lastDot + 1);
                    $end = $start + mb_strlen($piece);
                }
            }

            $trimmed = trim($piece);
            if ($trimmed !== '') {
                $chunks[] = $trimmed;
            }

            if ($end >= $len) {
                break;
            }

            $start = $end - self::CHUNK_OVERLAP;
        }

        return $chunks;
    }
}
