<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    private const MAX_TEXT_CHARS = 8000;
    private const MAX_FILE_KB = 20480; // 20 MB

    /**
     * Ekstrak metadata artikel dari teks PDF menggunakan DeepSeek.
     */
    public function parse(Request $request): JsonResponse
    {
        $text = trim((string) $request->input('text', ''));
        if ($text === '') {
            return response()->json(['error' => 'Teks PDF kosong.'], 422);
        }

        $text = mb_substr($text, 0, self::MAX_TEXT_CHARS);

        $data = $this->askDeepSeek($text);
        if ($data === null) {
            return response()->json(['error' => 'Gagal menghubungi model AI.'], 502);
        }

        return response()->json($data);
    }

    /**
     * Simpan file PDF yang diunggah ke storage agar bisa dilihat kembali.
     */
    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response()->json(['error' => 'File PDF wajib diunggah.'], 422);
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            return response()->json(['error' => 'Hanya file PDF yang didukung.'], 422);
        }

        if ($file->getSize() > self::MAX_FILE_KB * 1024) {
            return response()->json(['error' => 'Ukuran file maksimal 20 MB.'], 422);
        }

        $id = (string) Str::uuid();
        $dir = 'workspace/'.$request->user()->uuid;
        $name = 'wk-'.$id.'.pdf';

        // Ekstrak teks dari file sementara sebelum dipindah ke object storage.
        $extracted = $this->extractPdfText($file->getRealPath());

        $stored = Storage::disk('s3')->putFileAs($dir, $file, $name);
        if ($stored === false) {
            return response()->json(['error' => 'Gagal menyimpan file ke object storage.'], 500);
        }

        return response()->json([
            'id' => $id,
            'filename' => $file->getClientOriginalName(),
            'url' => '/api/workspace/files/'.$id,
            'text' => $extracted['text'],
            'pageCount' => $extracted['pageCount'],
        ], 201);
    }

    /**
     * Tampilkan file PDF yang tersimpan (inline, untuk dilihat di browser).
     */
    public function show(Request $request, string $id)
    {
        if (! preg_match('/^[a-f0-9\-]{36}$/i', $id)) {
            return response()->json(['error' => 'ID file tidak valid.'], 422);
        }

        $key = 'workspace/'.$request->user()->uuid.'/wk-'.$id.'.pdf';
        $disk = Storage::disk('s3');

        if (! $disk->exists($key)) {
            return response()->json(['error' => 'File tidak ditemukan.'], 404);
        }

        return response()->stream(function () use ($disk, $key) {
            $stream = $disk->readStream($key);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Hapus file PDF milik user dari object storage (hanya satu file tersebut).
     */
    public function destroy(Request $request, string $id)
    {
        if (! preg_match('/^[a-f0-9\-]{36}$/i', $id)) {
            return response()->json(['error' => 'ID file tidak valid.'], 422);
        }

        $key = 'workspace/'.$request->user()->uuid.'/wk-'.$id.'.pdf';
        $disk = Storage::disk('s3');

        if (! $disk->exists($key)) {
            return response()->json(['error' => 'File tidak ditemukan.'], 404);
        }

        $disk->delete($key);

        return response()->json(['message' => 'File dihapus.']);
    }

    /**
     * Ekstrak teks dari file PDF (server-side). Memakai zlib PHP (gzuncompress /
     * gzinflate) karena DecompressionStream browser tidak selalu bisa dekompresi
     * stream PDF tertentu.
     */
    private function extractPdfText(string $path): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return ['text' => '', 'pageCount' => 1];
        }

        $parts = [];
        $streamRe = "/stream\r?\n/";
        preg_match_all($streamRe, $bytes, $ms, PREG_OFFSET_CAPTURE);

        foreach ($ms[0] as $m) {
            $start = $m[1] + strlen($m[0]);
            $end = strpos($bytes, 'endstream', $start);
            if ($end === false) {
                break;
            }
            $raw = substr($bytes, $start, $end - $start);
            $head = substr($bytes, max(0, $m[1] - 200), 200);
            $data = $raw;

            if (strpos($head, '/FlateDecode') !== false) {
                $dec = @gzuncompress($raw);
                if ($dec === false) {
                    $dec = @gzinflate($raw);
                }
                if ($dec !== false) {
                    $data = $dec;
                }
            }

            $txt = $this->extractTextFromStream($data);
            if ($txt !== '') {
                $parts[] = $txt;
            }
        }

        $text = implode("\n", $parts);

        // Teks hasil ekstraksi berupa byte latin1 (WinAnsi/CP1252) yang bisa
        // mengandung byte 0x80-0xFF sehingga invalid UTF-8. Ubah ke UTF-8 agar
        // aman di-encode sebagai JSON.
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');

        $pageCount = substr_count($bytes, '/Type /Page');
        if ($pageCount === 0) {
            $pageCount = substr_count($bytes, '/Type/Page');
        }

        return ['text' => $text, 'pageCount' => max(1, $pageCount)];
    }

    /**
     * Ambil string yang menjadi bagian operator penampil teks (Tj/TJ/'/")
     * dari satu content stream yang sudah didekompresi.
     */
    private function extractTextFromStream(string $data): string
    {
        $parts = [];

        // (teks) Tj | (teks) ' | (teks) "
        if (preg_match_all('/\(((?:\\\\.|[^()\\\\])*)\)\s*(?:Tj|\'|")/', $data, $m1)) {
            foreach ($m1[1] as $t) {
                $t = $this->unescapePdf($t);
                if (trim($t) !== '') {
                    $parts[] = trim($t);
                }
            }
        }

        // [ (a) (b) ... ] TJ
        if (preg_match_all('/\[((?:\\\\.|[^\]\\\\])*)\]\s*TJ/', $data, $m2)) {
            foreach ($m2[1] as $inner) {
                if (preg_match_all('/\(((?:\\\\.|[^()\\\\])*)\)/', $inner, $m3)) {
                    foreach ($m3[1] as $t) {
                        $t = $this->unescapePdf($t);
                        if (trim($t) !== '') {
                            $parts[] = trim($t);
                        }
                    }
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Decode string literal PDF (escape octal dll) menjadi teks latin1.
     */
    private function unescapePdf(string $s): string
    {
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $n = $s[$i + 1];
                if ($n === 'n') { $out .= "\n"; $i++; continue; }
                if ($n === 'r') { $out .= "\r"; $i++; continue; }
                if ($n === 't') { $out .= "\t"; $i++; continue; }
                if ($n === '(' || $n === ')' || $n === '\\') { $out .= $n; $i++; continue; }
                $oct = substr($s, $i + 1, 3);
                if (preg_match('/^[0-7]{3}$/', $oct)) {
                    $out .= chr((int) octdec($oct));
                    $i += 3;
                    continue;
                }
                $out .= $n;
                $i++;
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /**
     * Panggil DeepSeek (OpenAI-compatible) untuk mengembalikan metadata JSON.
     */
    private function askDeepSeek(string $text): ?array
    {
        $system = <<<'PROMPT'
Anda adalah asisten yang mengekstrak metadata dari teks artikel ilmiah. Teks
yang diberikan berasal dari parsing PDF dan mungkin berantakan (ada spasi
antar-huruf, teks bilingual, atau metadata template yang menyesatkan).

Kembalikan HANYA satu objek JSON valid (tanpa teks lain) dengan struktur:

{
  "type": "article-journal",
  "title": "judul artikel",
  "authors": ["Nama Lengkap", "Nama Lengkap"],
  "year": "2023",
  "journal": "nama jurnal/sumber",
  "volume": "5",
  "issue": "2",
  "pages": "98-108",
  "doi": "10.xxxx/yyyy",
  "abstract": "teks abstrak",
  "keywords": ["kata kunci", "kata kunci"]
}

Aturan:
- type salah satu: article-journal, book, chapter, paper-conference, thesis, report, webpage.
- title: judul artikel sebenarnya, bukan nama jurnal/template/ISSN.
- authors: nama lengkap penulis saja, tanpa afiliasi/email/gelar.
- year: 4 digit. pages: rentang halaman artikel (mis. "98-108"), bukan jumlah halaman PDF.
- abstract: gabungkan kalimat abstrak yang terpisah-pisah menjadi satu paragraf rapi.
- Jika suatu field tidak ditemukan, isi dengan string kosong atau array kosong.
PROMPT;

        $response = Http::timeout(90)
            ->withToken((string) config('services.deepseek.api_key'))
            ->post(rtrim((string) config('services.deepseek.base_url'), '/').'/chat/completions', [
                'model' => (string) config('services.deepseek.model', 'deepseek-v4-flash'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $text],
                ],
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }
}
