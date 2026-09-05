<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaperController extends Controller
{
    /**
     * Cari paper/journal via Crossref REST API (proxy server-side).
     *
     * Tab yang didukung:
     *  - works    : /works (semua tipe work)
     *  - journals : /journals
     *  - other    : /works dengan filter tipe selain journal-article
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('query', ''));
        if ($query === '') {
            return response()->json(['error' => 'Kata kunci pencarian wajib diisi.'], 422);
        }

        $tab = (string) $request->input('tab', 'works');
        if (! in_array($tab, ['works', 'journals', 'other'], true)) {
            $tab = 'works';
        }

        $limit = min(max((int) $request->input('limit', 5), 1), 50);
        $email = $request->user()?->email ?: 'admin@tulisin.app';

        if ($tab === 'journals') {
            $items = $this->getItems('/journals', [
                'query' => $query,
                'rows' => $limit,
            ], $email);
        } elseif ($tab === 'other') {
            $items = $this->getItems('/works', [
                'query' => $query,
                'rows' => $limit,
                'select' => $this->workSelect(),
                'filter' => 'type:book,book-chapter,proceedings-article,proceedings,dataset,report,dissertation,monograph,reference-book,posted-content,standard',
            ], $email);
        } else {
            $items = $this->getItems('/works', [
                'query' => $query,
                'rows' => $limit,
                'select' => $this->workSelect(),
            ], $email);
        }

        if ($items === null) {
            return response()->json(['error' => 'Gagal mengambil data dari Crossref. Coba lagi sebentar.'], 502);
        }

        $papers = collect($items)
            ->map(fn (array $it) => $tab === 'journals' ? $this->normalizeJournal($it) : $this->normalizeWork($it))
            ->values()
            ->all();

        return response()->json([
            'query' => $query,
            'tab' => $tab,
            'total' => count($papers),
            'offset' => 0,
            'next' => null,
            'papers' => $papers,
        ]);
    }

    /**
     * Kolom yang benar-benar dipakai frontend untuk hasil "works".
     */
    private function workSelect(): string
    {
        return implode(',', [
            'DOI',
            'type',
            'title',
            'author',
            'abstract',
            'container-title',
            'published',
            'published-print',
            'published-online',
            'issued',
            'is-referenced-by-count',
            'URL',
            'link',
        ]);
    }

    /**
     * Ambil item dari Crossref. Mengembalikan null saat request gagal.
     */
    private function getItems(string $path, array $params, string $email): ?array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'Tulisin/1.0 (mailto:'.$email.')'])
                ->get(config('services.crossref.base_url', 'https://api.crossref.org').$path, $params);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json('message.items') ?? [];
    }

    /**
     * Normalisasi item /works menjadi struktur yang dipakai frontend.
     */
    private function normalizeWork(array $item): array
    {
        $authors = collect($item['author'] ?? [])
            ->map(function ($a) {
                $name = trim(($a['given'] ?? '').' '.($a['family'] ?? ''));
                return $name !== '' ? $name : ($a['name'] ?? '');
            })
            ->filter()
            ->values()
            ->all();

        $doi = $item['DOI'] ?? null;
        $title = $this->cleanText($item['title'][0] ?? 'Tanpa judul');

        $pdf = null;
        foreach ($item['link'] ?? [] as $link) {
            if (str_contains(strtolower((string) ($link['content-type'] ?? '')), 'pdf')) {
                $pdf = $link['URL'] ?? null;
                break;
            }
        }

        return [
            'id' => $doi ?: 'work-'.md5($title),
            'type' => 'work',
            'subtype' => $item['type'] ?? null,
            'title' => $title,
            'abstract' => $this->stripJats($item['abstract'] ?? null),
            'year' => $this->firstYear($item['published-print'] ?? $item['published-online'] ?? $item['published'] ?? $item['issued'] ?? null),
            'authors' => $authors,
            'venue' => $item['container-title'][0] ?? null,
            'doi' => $doi,
            'citationCount' => (int) ($item['is-referenced-by-count'] ?? 0),
            'openAccessPdf' => $pdf,
            'url' => $item['URL'] ?? ($doi ? 'https://doi.org/'.$doi : null),
        ];
    }

    /**
     * Normalisasi item /journals menjadi struktur yang dipakai frontend.
     */
    private function normalizeJournal(array $item): array
    {
        $issn = $item['ISSN'][0] ?? null;

        return [
            'id' => $issn ?: 'journal-'.md5($item['title'] ?? ''),
            'type' => 'journal',
            'subtype' => null,
            'title' => $this->cleanText($item['title'] ?? 'Tanpa judul'),
            'abstract' => null,
            'year' => null,
            'authors' => [],
            'venue' => $item['publisher'] ?? null,
            'doi' => null,
            'citationCount' => 0,
            'openAccessPdf' => null,
            'url' => $issn ? 'https://portal.issn.org/resource/ISSN/'.$issn : null,
        ];
    }

    /**
     * Ambil tahun dari struktur tanggal Crossref.
     */
    private function firstYear(?array $date): ?int
    {
        $year = $date['date-parts'][0][0] ?? null;
        return $year ? (int) $year : null;
    }

    /**
     * Bersihkan sisa tag HTML pada judul/abstrak.
     */
    private function cleanText(?string $text): string
    {
        $text = strip_tags((string) $text);
        return html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Abstrak Crossref berupa JATS XML; ambil teksnya saja.
     */
    private function stripJats(?string $abstract): ?string
    {
        $text = $this->cleanText($abstract);
        return $text !== '' ? $text : null;
    }
}
