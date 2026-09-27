<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien embedding generik (format OpenAI-compatible: POST {base_url}/embeddings).
 *
 * Dipakai untuk RAG Fase 1 (vector search). Dirancang graceful: bila provider
 * belum mendukung endpoint ini (mis. DeepSeek saat ini belum punya endpoint
 * embeddings resmi per Sept 2026, terverifikasi mengembalikan 404), method
 * mengembalikan null dan pemanggil (ReferenceIndexer) otomatis melewati
 * pengisian kolom `embedding`, sehingga retrieval tetap berjalan lexical-only
 * tanpa error. Begitu provider merilis endpoint embeddings (atau user mengisi
 * EMBEDDING_API_KEY/EMBEDDING_BASE_URL/EMBEDDING_MODEL provider lain di .env),
 * vector otomatis mulai terisi tanpa perubahan kode.
 */
class EmbeddingService
{
    /**
     * Buat embedding untuk satu teks. Mengembalikan null bila provider tidak
     * mendukung atau gagal (dicatat ke log, tidak melempar exception).
     *
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array
    {
        $result = $this->embedBatch([$text]);

        return $result[0] ?? null;
    }

    /**
     * Buat embedding untuk beberapa teks sekaligus (hemat request).
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>|null>  urutan sama dengan $texts; null per item yang gagal.
     */
    public function embedBatch(array $texts): array
    {
        $texts = array_values($texts);
        if ($texts === []) {
            return [];
        }

        $apiKey = (string) config('services.embedding.api_key');
        $baseUrl = rtrim((string) config('services.embedding.base_url'), '/');
        $model = (string) config('services.embedding.model');

        if ($apiKey === '' || $baseUrl === '' || $model === '') {
            return array_fill(0, count($texts), null);
        }

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withToken($apiKey)
                ->post($baseUrl.'/embeddings', [
                    'model' => $model,
                    'input' => $texts,
                    'encoding_format' => 'float',
                ]);
        } catch (\Throwable $e) {
            Log::warning('EmbeddingService: request gagal (jaringan)', ['error' => $e->getMessage()]);

            return array_fill(0, count($texts), null);
        }

        if (! $response->successful()) {
            // 404 = provider belum punya endpoint ini (kondisi normal untuk DeepSeek saat ini).
            // Cukup log sekali sebagai info, jangan spam warning tiap indexing.
            if ($response->status() === 404) {
                Log::info('EmbeddingService: provider belum mendukung endpoint /embeddings, lanjut lexical-only.');
            } else {
                Log::warning('EmbeddingService: provider menolak permintaan', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);
            }

            return array_fill(0, count($texts), null);
        }

        $data = $response->json('data', []);
        if (! is_array($data)) {
            return array_fill(0, count($texts), null);
        }

        // Petakan hasil berdasarkan `index` yang dikembalikan provider agar urutan tetap benar.
        $byIndex = [];
        foreach ($data as $item) {
            $idx = (int) ($item['index'] ?? -1);
            $vector = $item['embedding'] ?? null;
            if ($idx >= 0 && is_array($vector)) {
                $byIndex[$idx] = array_map('floatval', $vector);
            }
        }

        return array_map(fn ($i) => $byIndex[$i] ?? null, array_keys($texts));
    }

    /** Provider embedding aktif dikonfigurasi (bukan jaminan endpoint benar-benar ada). */
    public function isConfigured(): bool
    {
        return (string) config('services.embedding.api_key') !== '';
    }
}
