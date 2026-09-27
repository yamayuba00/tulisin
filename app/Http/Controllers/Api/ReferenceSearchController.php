<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cari referensi ilmiah lewat OpenAlex (gratis, tanpa API key) lalu simpan
 * hasilnya sebagai referensi Workspace agar bisa disitasi Agent AI.
 *
 * Alur yang didukung: user bertanya di Agent AI Canvas -> butuh sumber ->
 * buka tab "Cari Referensi" -> simpan yang cocok ke Workspace -> sumber itu
 * otomatis menjadi daftar "Referensi terverifikasi" untuk AI.
 */
class ReferenceSearchController extends Controller
{
    private const OPENALEX_BASE = 'https://api.openalex.org/works';

    // RAG bertahap: 10 hasil per halaman, maksimal 5 halaman (50 data).
    // Frontend memuat per 10 dan bisa generate ulang (halaman berikut) bila
    // 50 itu belum ada yang cocok.
    private const MAX_PER_PAGE = 10;

    private const MAX_PAGE = 5;

    /**
     * Cari karya ilmiah. Query: q (wajib), year_from, year_to, per_page (maks 10), page (maks 5).
     */
    public function search(Request $request): JsonResponse
    {
        if (! $request->user()->hasActiveSubscription()) {
            return response()->json(['error' => 'Fitur pencarian referensi memerlukan langganan aktif.'], 402);
        }

        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
        ]);

        $perPage = $data['per_page'] ?? self::MAX_PER_PAGE;
        $page = $data['page'] ?? 1;

        $query = [
            'search' => $data['q'],
            'per-page' => $perPage,
            'page' => $page,
            'select' => 'id,doi,title,publication_year,authorships,primary_location,best_oa_location,open_access,biblio,abstract_inverted_index,cited_by_count',
        ];

        $from = $data['year_from'] ?? null;
        $to = $data['year_to'] ?? null;
        if ($from !== null || $to !== null) {
            $filters = [];
            if ($from !== null) {
                $filters[] = 'from_publication_date:'.sprintf('%04d-01-01', $from);
            }
            if ($to !== null) {
                $filters[] = 'to_publication_date:'.sprintf('%04d-12-31', $to);
            }
            $query['filter'] = implode(',', $filters);
        }

        $mailto = (string) config('mail.from.address', '');
        if ($mailto !== '') {
            $query['mailto'] = $mailto;
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->withHeaders(['User-Agent' => 'Tulissin/1.0 ('.$mailto.')'])
                ->get(self::OPENALEX_BASE, $query);
        } catch (\Throwable) {
            return response()->json(['error' => 'Layanan pencarian referensi sedang tidak dapat dihubungi. Coba lagi.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['error' => 'Pencarian referensi gagal. Coba lagi.'], 502);
        }

        $results = array_map(
            fn (array $w) => $this->format($w),
            array_filter($response->json('results', []), fn ($w) => is_array($w)),
        );

        $totalCount = (int) $response->json('meta.count', 0);
        // Batasi total akumulasi 50 data (5 halaman x 10).
        $maxTotal = self::MAX_PER_PAGE * self::MAX_PAGE;
        $hasMore = $page < self::MAX_PAGE
            && ($page * $perPage) < $totalCount
            && ($page * $perPage) < $maxTotal;

        return response()->json([
            'results' => array_values($results),
            'total_count' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $hasMore,
            'max_total' => $maxTotal,
        ]);
    }

    /**
     * Simpan satu hasil pencarian sebagai referensi Workspace (CSL-JSON)
     * agar otomatis menjadi sumber terverifikasi bagi Agent AI.
     * Body: { work: {...hasil dari /search...} }.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->hasActiveSubscription()) {
            return response()->json(['error' => 'Fitur pencarian referensi memerlukan langganan aktif.'], 402);
        }

        $data = $request->validate([
            'work' => ['required', 'array'],
            'work.title' => ['required', 'string', 'max:500'],
            'work.authors' => ['nullable', 'array', 'max:30'],
            'work.year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'work.journal' => ['nullable', 'string', 'max:300'],
            'work.doi' => ['nullable', 'string', 'max:100'],
            'work.landing_url' => ['nullable', 'string', 'max:500'],
            'work.pdf_url' => ['nullable', 'string', 'max:500'],
            'work.abstract' => ['nullable', 'string', 'max:8000'],
            'work.volume' => ['nullable', 'string', 'max:20'],
            'work.issue' => ['nullable', 'string', 'max:20'],
            'work.pages' => ['nullable', 'string', 'max:30'],
            'work.cited_by_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $work = $data['work'];
        $doi = $this->cleanDoi((string) ($work['doi'] ?? ''));

        // Hindari duplikat: DOI yang sama cukup sekali per user.
        if ($doi !== '') {
            $existing = WorkspaceReference::query()
                ->where('user_id', $request->user()->id)
                ->where('data->>DOI', $doi)
                ->first();
            if ($existing) {
                return response()->json(array_merge(
                    ['id' => $existing->ref_id],
                    is_array($existing->data) ? $existing->data : [],
                    ['_already_saved' => true],
                ));
            }
        }

        $refId = 'ws_'.Str::random(12);

        $item = [
            'id' => $refId,
            'type' => 'article-journal',
            'title' => trim((string) $work['title']),
            'author' => $this->authors($work['authors'] ?? []),
            'issued' => ['date-parts' => [[(string) ($work['year'] ?? '')]]],
            'container-title' => trim((string) ($work['journal'] ?? '')),
            'volume' => trim((string) ($work['volume'] ?? '')),
            'issue' => trim((string) ($work['issue'] ?? '')),
            'page' => trim((string) ($work['pages'] ?? '')),
            'DOI' => $doi,
            'URL' => trim((string) ($work['landing_url'] ?? '')),
            '_abstract' => trim((string) ($work['abstract'] ?? '')),
            '_keywords' => [],
            '_snippet' => '',
            '_filename' => '',
            '_fileId' => '',
            '_fileUrl' => trim((string) ($work['pdf_url'] ?? '')),
            '_source' => 'openalex',
            '_citedBy' => (int) ($work['cited_by_count'] ?? 0),
            '_addedAt' => (int) (microtime(true) * 1000),
        ];

        WorkspaceReference::updateOrCreate(
            ['user_id' => $request->user()->id, 'ref_id' => $refId],
            ['data' => $item],
        );

        return response()->json($item, 201);
    }

    /**
     * Normalisasi satu work OpenAlex ke bentuk ringkas untuk frontend.
     */
    private function format(array $work): array
    {
        $doi = $this->cleanDoi((string) ($work['doi'] ?? ''));
        $bestOa = is_array($work['best_oa_location'] ?? null) ? $work['best_oa_location'] : [];
        $primary = is_array($work['primary_location'] ?? null) ? $work['primary_location'] : [];
        $primarySource = is_array($primary['source'] ?? null) ? $primary['source'] : [];
        $biblio = is_array($work['biblio'] ?? null) ? $work['biblio'] : [];

        $authors = [];
        foreach ($work['authorships'] ?? [] as $a) {
            $name = is_array($a['author'] ?? null) ? (string) ($a['author']['display_name'] ?? '') : '';
            if ($name !== '') {
                $authors[] = ['name' => $name];
            }
        }

        return [
            'key' => (string) ($work['id'] ?? ''),
            'title' => (string) ($work['title'] ?? 'Tanpa Judul'),
            'authors' => array_slice($authors, 0, 10),
            'year' => (int) ($work['publication_year'] ?? 0),
            'journal' => (string) ($primarySource['display_name'] ?? ''),
            'doi' => $doi,
            'landing_url' => $doi !== ''
                ? 'https://doi.org/'.$doi
                : (string) ($primary['landing_page_url'] ?? $work['id'] ?? ''),
            'pdf_url' => (string) ($bestOa['pdf_url'] ?? ''),
            'is_oa' => (bool) (($work['open_access']['is_oa'] ?? false) || ($bestOa['pdf_url'] ?? null)),
            'abstract' => $this->abstract($work['abstract_inverted_index'] ?? null),
            'volume' => (string) ($biblio['volume'] ?? ''),
            'issue' => (string) ($biblio['issue'] ?? ''),
            'pages' => trim((string) ($biblio['first_page'] ?? '').'-'.(string) ($biblio['last_page'] ?? ''), '-'),
            'cited_by_count' => (int) ($work['cited_by_count'] ?? 0),
        ];
    }

    /**
     * Bangun ulang abstrak dari inverted index OpenAlex.
     */
    private function abstract(mixed $index): string
    {
        if (! is_array($index) || $index === []) {
            return '';
        }

        $words = [];
        foreach ($index as $word => $positions) {
            if (! is_array($positions)) {
                continue;
            }
            foreach ($positions as $pos) {
                $words[(int) $pos] = (string) $word;
            }
        }
        ksort($words);

        $text = implode(' ', $words);

        return mb_strlen($text) > 1500 ? mb_substr($text, 0, 1500).'…' : $text;
    }

    /**
     * Bersihkan DOI dari prefix URL bila ada.
     */
    private function cleanDoi(string $doi): string
    {
        $doi = trim($doi);
        $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi) ?? $doi;

        return trim($doi);
    }

    /**
     * Ubah daftar nama "Depan Belakang" menjadi penulis CSL {family, given}.
     */
    private function authors(mixed $authors): array
    {
        if (! is_array($authors)) {
            return [];
        }

        $out = [];
        foreach ($authors as $a) {
            $name = trim((string) (is_array($a) ? ($a['name'] ?? '') : $a));
            if ($name === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $name) ?: [];
            if (count($parts) === 1) {
                $out[] = ['family' => $parts[0], 'given' => ''];
            } else {
                $out[] = [
                    'family' => array_pop($parts),
                    'given' => implode(' ', $parts),
                ];
            }
        }

        return $out;
    }
}
