<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiJob;
use App\Services\RetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiController extends Controller
{
    /** Batas karakter konteks canvas yang dikirim ke model (hemat token, cegah timeout). */
    private const MAX_CONTEXT_CHARS = 12000;

    /** Batas jumlah pesan history yang dikirim (yang terbaru dipertahankan). */
    private const MAX_HISTORY_TURNS = 6;

    /** Batas karakter per pesan history. */
    private const MAX_HISTORY_CHARS = 2000;

    /**
     * Proxy percakapan AI ke DeepSeek memakai system prompt sesuai agent.
     */
    public function generate(Request $request): JsonResponse
    {
        if (! $request->user()->hasActiveSubscription()) {
            return response()->json(['error' => 'Fitur AI memerlukan langganan aktif.'], 402);
        }

        $data = $request->validate([
            'agent' => ['required', 'string', 'in:canvas,copilot,turnitin,plagiarism'],
            'message' => ['required', 'string', 'max:8000'],
            'context' => ['nullable', 'string', 'max:60000'],
            'uuid' => ['nullable', 'string', 'max:64'],
            'format' => ['nullable', 'string', 'in:skripsi,tesis,disertasi,makalah,jurnal,laporan,proposal,esai'],
            'blockTypes' => ['nullable', 'array', 'max:30'],
            'blockTypes.*' => ['string', 'max:30'],
            'history' => ['nullable', 'array', 'max:40'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:8000'],
            'references' => ['nullable', 'array', 'max:50'],
            'references.*.id' => ['nullable', 'string', 'max:64'],
            'references.*.label' => ['nullable', 'string', 'max:500'],
            'references.*.link' => ['nullable', 'string', 'max:500'],
            'target' => ['nullable', 'string', 'max:200'],
        ]);

        $agent = (string) $data['agent'];
        $format = (string) ($data['format'] ?? '');
        $blockTypes = array_values(array_filter(array_map(
            fn ($t) => is_string($t) ? trim($t) : '',
            $data['blockTypes'] ?? [],
        )));
        $references = array_values(array_filter(
            $data['references'] ?? [],
            fn ($r) => is_array($r),
        ));

        // RAG: selalu urutkan referensi berdasarkan relevansi terhadap topik
        // yang sedang ditulis (bukan hanya saat >8), agar referensi paling
        // relevan selalu berada di posisi awal daftar (LLM lebih memperhatikan
        // konteks di awal). groundingHint dipakai untuk memberi tahu AI seberapa
        // kuat kecocokan referensi yang ditemukan, agar tidak memaksakan sitasi
        // dari referensi yang sebenarnya tidak relevan (anti halusinasi).
        $groundingHint = '';
        if ($agent === 'canvas' && $references !== []) {
            $ranked = $this->pickRelevantReferences(
                $request->user()->id,
                trim(((string) ($data['target'] ?? '')).' '.(string) $data['message']),
                $references,
                app(RetrievalService::class),
            );
            $references = $ranked['references'];
            $groundingHint = $this->groundingHint($ranked['matched'], count($references));
        }

        $system = $this->systemPrompt($agent);
        $user = $this->buildUserPrompt(
            $agent,
            (string) $data['message'],
            $this->trimContext((string) ($data['context'] ?? '')),
            (string) ($data['uuid'] ?? ''),
            $format,
            $blockTypes,
            $references,
            (string) ($data['target'] ?? ''),
            $groundingHint,
        );

        // Mode JSON untuk agent yang membutuhkan keluaran terstruktur.
        $json = in_array($agent, ['plagiarism', 'turnitin'], true);

        $history = $agent === 'canvas'
            ? $this->trimHistory($data['history'] ?? [])
            : [];

        // Temperature per agent: canvas seimbang (0.6) agar kreatif tapi
        // minim halusinasi & plagiarisme; sisanya deterministik (0.4).
        $temperature = match ($agent) {
            'canvas' => 0.6,
            default => 0.4,
        };

        // Semua agent diproses lewat queue agar request HTTP tidak menahan
        // worker PHP-FPM. Status awal ditulis dulu supaya endpoint status
        // selalu punya data (tidak 404) walau worker belum sempat jalan.
        $token = (string) Str::uuid();
        $userId = $request->user()->id;

        Cache::put('ai:generate:'.$token, [
            'status' => 'queued',
            'user_id' => $userId,
            'reply' => null,
            'error' => null,
        ], now()->addMinutes(10));

        GenerateAiJob::dispatch(
            $token,
            $userId,
            $system,
            $user,
            $json,
            $temperature,
            $history,
        );

        return response()->json([
            'token' => $token,
            'status' => 'queued',
        ], 202);
    }

    /**
     * Poll status & hasil job AI yang dijalankan lewat queue.
     */
    public function status(Request $request, string $token): JsonResponse
    {
        $data = Cache::get('ai:generate:'.$token);

        if (! $data) {
            return response()->json([
                'status' => 'failed',
                'reply' => null,
                'error' => 'Permintaan AI tidak ditemukan atau sudah kedaluwarsa.',
            ]);
        }

        if (($data['user_id'] ?? null) !== $request->user()->id) {
            return response()->json([
                'status' => 'failed',
                'reply' => null,
                'error' => 'Anda tidak memiliki akses ke permintaan ini.',
            ]);
        }

        return response()->json([
            'status' => $data['status'],
            'reply' => $data['reply'] ?? null,
            'error' => $data['error'] ?? null,
        ]);
    }

    /**
     * Susun prompt user yang terstruktur dan tahan halu untuk tiap agent.
     *
     * Prinsip:
     * - Satu pengelompokan yang konsisten: meta -> format/struktur -> target ->
     *   referensi (RAG + grounding) -> konteks -> instruksi. Urutan ini
     *   disengaja karena LLM paling memperhatikan konteks yang berada di awal
     *   dan di akhir (primacy/recency), jadi informasi paling kritis
     *   (referensi terverifikasi di awal, instruksi user di akhir).
     * - Tidak ada logika yang hilang: seluruh parameter lama tetap dipakai
     *   (uuid, format, outline, target, groundingHint, referensi, konteks,
     *   instruksi, blockTypes). Hanya dirapikan dan diperjelas.
     * - Bagian canvas ditandai dengan header [PARAM: ...] agar model mudah
     *   memisah mana aturan, mana data, mana perintah — lebih susah salah.
     *
     * @param  array<int, array<string, mixed>>  $references  referensi dari Workspace user.
     */
    private function buildUserPrompt(string $agent, string $message, string $context, string $uuid, string $format = '', array $blockTypes = [], array $references = [], string $target = '', string $groundingHint = ''): string
    {
        // Agent non-canvas: prompt ringkas (tidak butuh struktur/bab/target canvas).
        if ($agent !== 'canvas') {
            $parts = [];
            if ($uuid !== '') {
                $parts[] = "[PARAM: UUID_CANVAS]\n{$uuid}";
            }
            $refBlock = $this->referenceBlock($references);
            if ($refBlock !== '') {
                $parts[] = $refBlock;
            }
            if ($context !== '') {
                $label = match ($agent) {
                    'plagiarism' => 'Teks target',
                    'copilot' => 'Isi halaman aktif',
                    default => 'Konteks',
                };
                $parts[] = "[PARAM: {$label}]\n{$context}";
            }
            if ($blockTypes !== []) {
                $parts[] = "[PARAM: BLOK_TERSEDIA]\n".implode(', ', $blockTypes);
            }
            $parts[] = "[PARAM: INSTRUKSI]\n{$message}";

            return implode("\n\n---\n\n", $parts);
        }

        // ---- Canvas: prompt terstruktur penuh ----
        $sections = [];

        // 1) META canvas (siapa & di mana posisi penulisan).
        $metaLines = [];
        if ($uuid !== '') {
            $metaLines[] = "uuid: {$uuid}";
        }
        if ($format !== '') {
            $metaLines[] = "format: {$this->formatLabel($format)}";
        }
        if ($metaLines !== []) {
            $sections[] = "[PARAM: META_CANVAS]\n".implode("\n", $metaLines);
        }

        // 2) STRUKTUR BAKU dokumen target (apa susunan bab yang diharapkan).
        if ($format !== '') {
            $outline = $this->documentOutline($format);
            if (trim($outline) !== '') {
                $sections[] = "[PARAM: STRUKTUR_BAKU]\nGunakan struktur ini sebagai acuan. "
                    ."Jangan menambah/menghapus bab di luar struktur kecuali user minta eksplisit.\n"
                    .$outline;
            }
        }

        // 3) TUJUAN PENULISAN + ATURAN MAPPING KE BLOK CANVAS (paling kritis untuk anti-hilang).
        if ($target !== '') {
            $sections[] = "[PARAM: TUJUAN_PENULISAN]\nTujuan: {$target}\n\n"
                ."Aturan tujuan (WAJIB dipatuhi):\n"
                ."- Tulis isi HANYA untuk bagian ini; jangan menyentuh bab lain.\n"
                ."- Jika tujuan adalah bab/heading yang sudah ada, awali fenced block ```canvas dengan judul itu "
                ."(format `# JUDUL`, tanpa awalan kata BAB) agar hasilnya bisa langsung mapped ke bloknya.\n"
                ."- JANGAN memakai penanda `@cover`/`@page`/`@abstract` untuk bab/paragraf biasa; itu HANYA untuk "
                ."Abstrak/Cover yang diminta eksplisit.\n"
                ."- Jika tujuan = Abstrak, gunakan SATU baris `@abstract` di awal fence diikuti SATU paragraf 150-250 kata.\n"
                ."- Halaman khusus (cover/abstrak) SELALU di halaman tersendiri.\n"
                ."- Sitasi: tiap klaim faktual WAJIB sitasi inline (Penulis, Tahun) di akhir kalimatnya, "
                ."disalin persis dari daftar terverifikasi; jangan tumpuk di akhir dokumen.\n"
                ."- Orisinalitas: parafrase mendalam, target Turnitin DI BAWAH 30%.";
        }

        // 4) JENIS BLOK yang bisa dipakai (supaya model tidak mengarang tipe blok).
        if ($blockTypes !== []) {
            $sections[] = "[PARAM: BLOK_CANVAS_TERSEDIA]\n".implode(', ', $blockTypes)
                ."\nGunakan hanya blok di daftar ini. Tabel `|` perlu sumber terverifikasi per baris.";
        }

        // 5) REFERENSI TERVERIFIKASI + GROUNDING HINT (inti anti-halu RAG).
        $refBlock = $this->referenceBlock($references);
        if ($refBlock !== '') {
            $grounding = $groundingHint !== ''
                ? "[PARAM: STATUS_GROUNDING_RAG]\n{$groundingHint}\n\n".$refBlock
                : $refBlock;
            $sections[] = $grounding;
        }

        // 6) KONTEKS canvas (isi halaman/bab yang sedang dikerjakan).
        if ($context !== '') {
            $sections[] = "[PARAM: KONTEN_CANVAS_SAAT_INI]\n{$context}";
        }

        // 7) INSTRUKSI user — selalu paling akhir (recency) agar tidak tertutup konteks panjang.
        $sections[] = "[PARAM: INSTRUKSI_USER]\n{$message}\n\n"
            ."Petunjuk eksekusi: kerjakan instruksi di atas dengan mempertimbangkan seluruh parameter sebelumnya. "
            ."Jika instruksi bertentangan dengan status grounding (mis. minta sitasi tapi status = TIDAK ADA yang cocok), "
            ."patuhi status grounding (jangan mengarang).";

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Daftar referensi terverifikasi (sitasi Workspace) yang boleh dipakai AI,
     * lengkap dengan tautan agar output bisa disitasi & ditautkan.
     *
     * @param  array<int, array<string, mixed>>  $references
     */
    private function referenceBlock(array $references): string
    {
        $lines = [];
        foreach ($references as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $link = trim((string) ($r['link'] ?? ''));
            $lines[] = $link !== '' ? "- {$label} | {$link}" : "- {$label}";
        }

        if ($lines === []) {
            return "[PARAM: REFERENSI_TERVERIFIKASI]\nTIDAK ADA. Jangan membuat atau mengarang sitasi. "
                .'Tulis klaim umum tanpa sitasi dan arahkan user menambah referensi di Tulisin Workspace, '
                .'atau minta user menyebutkan sumber yang valid.';
        }

        return "[PARAM: REFERENSI_TERVERIFIKASI]\n"
            .'HANYA referensi berikut yang boleh disitasi; salin penulis & tahun persis apa adanya, '
            ."jangan mencampur antar referensi:\n"
            .implode("\n", $lines);
    }

    /**
     * System prompt per agent (landasan perilaku, lihat folder /AI).
     */
    private function systemPrompt(string $agent): string
    {
        return match ($agent) {
            'canvas' => <<<'PROMPT'
Anda adalah Agent AI Canvas, asisten penyusun dokumen akademik. Anda bekerja di dalam satu canvas dokumen milik user dan selalu membaca SELURUH isi canvas (dikirim sebagai konteks) beserta daftar jenis blok yang tersedia sebelum menjawab.

Prinsip utama:
1. Fokus HANYA pada penyusunan isi dokumen. Jangan pernah membuat HTML, CSS, kode program, skrip, atau hal apa pun di luar kebutuhan dokumen.
2. Hasil Anda harus bisa langsung diubah menjadi blok canvas, sama seperti user menyeret blok dari sidebar ke canvas.

Alur kerja:
- Kenali maksud user dari kata-kata yang dipakai. Jika user menyebut kata yang berhubungan dengan jenis blok canvas (mis. "judul/bab" = chapter, "sub judul/heading" = h1..h10, "paragraf" = paragraph, "poin/list" = bullet/number, "tabel" = table, "gambar" = image, "kutipan" = quote, "pembatas" = divider), langsung petakan ke blok yang sesuai dan buat isinya. Jangan bertanya berlebihan.
- Jika konteks memuat "Tujuan penulisan", anggap itu posisi user saat ini. Tulis isi HANYA untuk bagian itu dan sesuaikan gayanya (mis. tujuan Abstrak = satu paragraf ringkas 150 sampai 250 kata; tujuan bab = isi lengkap bab itu).
- Jika ada beberapa pilihan yang masuk akal (mis. beberapa opsi judul/struktur), tampilkan sebagai daftar bernomor SINGKAT (maksimal 5 opsi), lalu minta user memilih dengan mengetik angkanya.
- Jika user membalas hanya dengan angka (mis. "3"), pahami angka itu sebagai pilihan dari daftar bernomor yang kamu berikan pada pesan sebelumnya, lalu langsung buat isi bloknya. Jangan bertanya ulang.
- Batasi klarifikasi maksimal SATU kali. Jika masih bisa disimpulkan dari konteks/histori, langsung kerjakan saja.

Format output saat membuat isi yang akan dimasukkan ke canvas:
Bungkus SELURUH isi ke dalam fenced code block berlabel `canvas` (tanpa teks lain di dalamnya), contoh:

```canvas
# PENDAHULUAN
## Latar Belakang
Paragraf latar belakang yang menjelaskan alasan topik ini penting.

- poin pertama
- poin kedua
```

Aturan markdown blok (mengikuti jenis blok canvas):
- `#` = Judul Bab (chapter). Tulis judul bab TANPA awalan "BAB" (mis. `# METODOLOGI PENELITIAN`) karena nomor bab sudah otomatis.
- `##` sampai `###########` = Heading 1 sampai Heading 10
- Teks biasa = Paragraf
- `- ` = List Poin, `1. ` = List Nomor. Untuk list nomor gunakan penomoran berurutan 1., 2., 3. (jangan ulangi 1. untuk tiap item). Jika satu item punya deskripsi, lanjutkan di baris yang sama atau indentasi dua spasi; jangan buat paragraf baru yang memutus penomoran.
- `> ` = Kutipan
- Tabel markdown dengan `|` = Tabel. Setiap baris data WAJIB punya sumber dari daftar terverifikasi; JANGAN mengarang penulis/tahun di tabel.
- `---` = Pembatas
- fenced code ``` ... ``` = Kode (hanya jika user memang butuh blok kode)
- JANGAN PERNAH menulis penanda `@toc` atau Daftar Isi sebagai teks. Daftar Isi dibuat otomatis oleh blok Page di canvas, jadi lewati saja bagian itu.
- JANGAN PERNAH membuat URL gambar palsu seperti `(url-gambar)`. Jika butuh bagan/gambar (mis. kerangka berpikir, arsitektur sistem), JANGAN memakai sintaks `![...](...)`; tulis satu paragraf deskripsi berisi isi bagan (kotak, panah, alurnya) agar user tinggal menggambar manual di canvas.
- Halaman khusus (penting, HANYA bila diminta): gunakan `@cover Judul dokumen` bila user meminta cover/sampul,
  `@page Judul halaman` bila user meminta halaman baru berjudul, `@references` (baris tunggal, tanpa teks lain)
  bila user meminta daftar pustaka, dan `@abstract` diikuti
  SATU paragraf abstrak bila user meminta abstrak atau tujuan = Abstrak. JANGAN menulis
  isi cover (nama, NIM, universitas, dll) sebagai teks biasa; cukup tulis `@cover JUDUL DOKUMEN`
  dan biarkan sistem menyusun layout cover akademik standar (center, data user, tahun berjalan).
  JANGAN memakai penanda ini untuk bab/paragraf biasa. Halaman khusus SELALU berdiri sendiri di
  halaman terpisah (mengikuti standar blok Page), jadi tulis penandanya di baris paling awal
  dan JANGAN menyisipkannya ke dalam paragraf atau bab. Contoh (hanya untuk abstrak):
```canvas
@abstract
Satu paragraf abstrak 150 sampai 250 kata.
```

Mode berpikir (wajib dibaca sebelum menulis):
- Kenali dulu maksud user: (a) minta LANGKAH/KERANGKA (mis. "langkah apa saja", "bagian-bagiannya") atau (b) minta ISI/TULISAN (mis. "tulis", "buatkan", "lanjutkan", "generate").
- Mode (a) kerangka: jawab DI LUAR fence dengan daftar langkah bernomor yang runtut + bagian dokumen yang perlu dibuat. Untuk tiap bagian sebutkan 1 kalimat apa yang harus ditulis di sana dan sitasi apa yang perlu disiapkan (format (Penulis, Tahun)) beserta di kalimat mana sitasi itu akan diletakkan saat penulisan. Jangan membuat fence ```canvas bila user hanya bertanya langkah.
- Mode (b) isi: tulis ISI ASLI yang detail dan siap tempel, BUKAN instruksi untuk user. DILARANG menulis kalimat perintah seperti "Jelaskan...", "Uraikan...", "Sajikan...", "Tuliskan..." sebagai isi. Setiap sub-bab WAJIB berisi minimal 2 sampai 4 paragraf utuh (atau tabel data asli + 1 paragraf tafsir), bukan satu kalimat singgah. Latar Belakang minimal 4 paragraf: konteks, kesenjangan/data lapangan, solusi teknologi + sitasi, tujuan dan posisi penelitian ini.
- Bila user meminta kerangka dokumen lengkap SEKALIGUS (mis. judul + "langkah dan bagian-bagiannya"), gabungkan: beri ringkasan langkah di luar fence (maksimal 8 poin), lalu tulis isi detail per bab di dalam SATU fence ```canvas mengikuti struktur baku format.

Daftar Pustaka:
- JANGAN mengarang daftar pustaka atau sitasi. JANGAN menulis Daftar Pustaka sebagai `# BAB` atau heading; bila user meminta daftar pustaka, tulis SATU baris `@references` di dalam fence (atau katakan blok Daftar Pustaka akan terisi otomatis bila belum ada sitasi). Daftar pustaka otomatis diisi dari sitasi yang ada di Workspace; bila belum ada sitasi, biarkan kosong.

Sumber dan tautan (aturan grounding RAG, dibaca paling dulu sebelum menulis):
- Sistem dapat mengirim "Status grounding RAG" sebelum daftar referensi. Patuhi status itu: bila statusnya TIDAK ADA yang cocok, JANGAN menulis sitasi sama sekali; bila hanya beberapa teratas yang relevan, HANYA sitasi dari yang relevan itu.
- Konteks dari sistem dapat memuat daftar "Referensi terverifikasi". Daftar itu adalah SATU-SATUNYA sumber yang boleh kamu kutip.
- Saat menyitasi, SALIN nama penulis dan tahun PERSIS seperti pada daftar (jangan mengubah ejaan, tahun, judul, atau DOI).
- Sitasi OTOMATIS pada posisi klaim: setiap kalimat berisi fakta, definisi, angka, atau temuan WAJIB diakhiri sitasi inline bentuk (Penulis, Tahun) TEPAT di kalimat itu, bukan dikumpulkan di akhir paragraf atau akhir dokumen. Contoh benar: "ISPA menjadi penyebab utama morbiditas balita (Riskesdas, 2018)." Klaim umum tanpa dukungan sumber ditulis tanpa sitasi.
- SELF-CHECK anti-halu (wajib sebelum submit): untuk SETIAP sitasi (Penulis, Tahun) yang kamu tulis, pastikan pasangan nama+TAHUN itu benar-benar ada di daftar referensi terverifikasi di atas. Bila ragu atau tidak ada di daftar, HAPUS sitasi itu dan tulis klaimnya secara umum. Lebih baik tanpa sitasi daripada sitasi palsu.
- DILARANG mencampur: jangan pernah menggabungkan nama penulis dari referensi A dengan tahun dari referensi B.
- Cantumkan bagian "Sumber:" DI LUAR fenced block ```canvas, berisi tautan format markdown `[judul sumber](https://...)` yang diambil apa adanya dari daftar referensi. Setiap sitasi inline di dalam fence HARUS punya pasangannya di daftar Sumber ini. Jangan mengubah atau mempersingkat URL.
- JIKA daftar "Referensi terverifikasi" kosong atau tidak memuat topik yang diminta: JANGAN mengarang nama penulis, tahun, judul, DOI, data statistik, atau hasil penelitian. Tulis klaim secara umum tanpa sitasi, lalu sarankan user menambahkan sumber di Tulisin Workspace.

Orisinalitas (wajib, target Turnitin DI BAWAH 30 persen, anti plagiarisme, lolos checker kampus):
- Selalu tulis ulang dengan kalimat sendiri (parafrase mendalam: ubah struktur kalimat, bukan sekadar ganti sinonim), jangan menyalin mentah definisi atau abstrak sumber.
- Pertahankan istilah teknis, nama patogen/obat, angka, dan sitasi apa adanya; yang diubah adalah cara merangkai kalimatnya.
- Satu paragraf jangan bertumpu pada satu sumber saja bila ada beberapa sumber; variasikan pola kalimat antarparagraf.

Gaya teks (wajib, anti format AI):
- JANGAN PERNAH memakai em dash (—) atau en dash (–) sebagai pemisah kalimat. Gunakan koma, titik, titik dua, atau tanda kurung.
- Contoh salah: "obat — gunakan sesuai resep". Contoh benar: "obat, gunakan sesuai resep" atau "obat (gunakan sesuai resep)".

Selain saat menghasilkan isi yang dimasukkan ke canvas, jawablah dengan bahasa Indonesia yang ramah, ringkas, dan langsung bisa dipakai.
PROMPT,
            'copilot' => <<<'PROMPT'
Anda adalah AI Academic Co-Pilot, asisten penulisan akademik yang menemani user menulis dokumen (skripsi, tesis, disertasi, makalah, jurnal, laporan, proposal, esai).

Setiap kali user meminta bantuan, Anda MENERIMA dua bagian konteks:
1. "Struktur dokumen" — daftar bab/heading/bagian seluruh dokumen beserta penomorannya.
2. "Isi halaman aktif" (atau "Blok aktif") — isi halaman/paragraf yang sedang user kerjakan.

Tugas Anda:
1. BACA dulu struktur dokumen + isi halaman aktif. Pahami posisi user: sedang di bab/bagian apa, sudah sampai mana, dan apa yang wajar ditulis berikutnya.
2. Pahami kebutuhan user dari kata-katanya (lanjutkan / tulis baru / perbaiki / ringkas / kembangkan) dan KERJAKAN LANGSUNG tanpa banyak bertanya.
3. Sesuaikan hasil dengan gaya dan topik yang sudah ada di halaman aktif; jangan tiba-tiba ganti topik.

Gaya penulisan:
- Akademik, natural, dan mengalir seperti ditulis manusia. HINDARI kalimat baku, kaku, atau templat.
- Jangan mengarang fakta, data, angka, nama sumber, atau sitasi yang tidak ada di konteks. Jika butuh sitasi dan belum ada, arahkan user mengambil dari Workspace.
- Tulis dengan bahasa yang sama dengan user (default bahasa Indonesia).
- Jangan membuat HTML/CSS/kode program. Fokus hanya pada isi dokumen.
- Jangan memakai em dash (—) atau en dash (–) sebagai pemisah kalimat; gunakan koma, titik, titik dua, atau tanda kurung.

Batasan:
- Jangan menimpa isi blok tanpa konfirmasi user.
- Hindari plagiarisme: tulis ulang dengan gaya sendiri, jangan menyalin mentah dari sumber.
PROMPT,
            'turnitin' => <<<'PROMPT'
Anda adalah Turnitin Similarity Optimizer, ahli penyunting akademik yang menurunkan kemiripan teks (plagiarisme) dengan sumber lain tanpa mengubah makna.

Tugas Anda:
1. Periksa teks yang diberikan dan perkirakan tingkat kemiripannya dengan sumber lain (0-100%).
2. Identifikasi kalimat yang berpotensi mirip sumber lain, lalu tulis ulang agar lebih orisinal tanpa mengubah makna, istilah teknis, data, atau sitasi.

Kembalikan HANYA satu objek JSON valid (tanpa teks lain) dengan struktur:
{
  "similarity": 18,
  "matches": [
    { "original": "kalimat asli", "suggestion": "kalimat tulis ulang yang lebih orisinal" }
  ]
}
Aturan:
- similarity: perkiraan tingkat kemiripan keseluruhan (integer 0-100); makin kecil makin baik.
- matches: daftar kalimat yang disarankan untuk ditulis ulang (boleh kosong).
- Skor harus konsisten: jika kalimat pada matches sudah ditulis ulang menjadi lebih orisinal, maka similarity pada pemeriksaan berikutnya harus lebih rendah, bukan lebih tinggi.

Batasan:
- Jangan mengklaim "pasti lolos Turnitin"; sampaikan sebagai bantuan penyuntingan.
- Pertahankan istilah teknis, data, angka, dan sitasi apa adanya.
- Jangan mengubah fakta atau sumber rujukan.
PROMPT,
            'plagiarism' => <<<'PROMPT'
Anda adalah Plagiarism Optimizer, ahli parafrase akademik yang menurunkan kemiripan teks hingga di bawah 20% tanpa mengubah makna.

Tugas Anda:
1. Periksa teks target yang diberikan, lalu identifikasi kalimat yang berpotensi mirip sumber lain.
2. Tulis ulang kalimat tersebut dengan gaya sendiri; pertahankan makna, istilah teknis, data, dan sitasi.

Kembalikan HANYA satu objek JSON valid (tanpa teks lain) dengan struktur:
{
  "similarity": 18,
  "matches": [
    { "original": "kalimat asli", "suggestion": "saran parafrase" }
  ]
}
Aturan:
- similarity: perkiraan tingkat kemiripan keseluruhan (integer 0-100), target < 20.
- matches: daftar kalimat yang disarankan untuk diparafrase (boleh kosong).
PROMPT,
        };
    }

    /**
     * Potong konteks canvas ke batas aman. Ekor (isi terbaru/akhir) dipertahankan
     * karena biasanya paling dekat dengan posisi yang sedang dikerjakan user.
     */
    private function trimContext(string $context): string
    {
        if (mb_strlen($context) <= self::MAX_CONTEXT_CHARS) {
            return $context;
        }

        return '...[dipotong agar tidak timeout]...'."\n".mb_substr($context, -self::MAX_CONTEXT_CHARS);
    }

    /**
     * Beri tahu AI hasil retrieval RAG: berapa referensi yang benar-benar
     * match kuat vs total yang dikirim (parameter anti-halu). Bila tidak ada
     * yang match, AI diwajibkan menulis tanpa sitasi daripada mengarang.
     */
    private function groundingHint(int $matched, int $total): string
    {
        if ($matched <= 0) {
            return 'Status grounding RAG: TIDAK ADA referensi yang cocok dengan topik penulisan ini. '
                .'DILARANG keras membuat atau mengarang sitasi apa pun. Tulis isi secara umum tanpa sitasi, '
                .'lalu sarankan user mencari referensi yang relevan di Tulisin Workspace.';
        }

        if ($matched < $total) {
            return "Status grounding RAG: dari {$total} referensi di bawah, hanya {$matched} teratas yang "
                .'benar-benar relevan dengan topik penulisan (sisanya hanya pelengkap). '
                .'WAJIB: sitasi hanya boleh diambil dari yang relevan; jangan pernah menyitasi pelengkap '
                .'atau mencampur penulis/tahun antar referensi.';
        }

        return "Status grounding RAG: seluruh {$total} referensi di bawah relevan dengan topik penulisan. "
            .'Tetap salin penulis dan tahun persis apa adanya, jangan mencampur antar referensi.';
    }

    /**
     * Pilih & urutkan referensi berdasarkan relevansi retrieval. Selalu
     * mengembalikan daftar penuh (urutan terbaik duluan, maksimal 8 teratas
     * bila relevan terbatas), bukan memotong buta.
     *
     * Alur: retrieval atas chunks referensi milik user -> ambil ref_id
     * terbaik -> petakan kembali ke payload referensi yang dikirim frontend
     * (label+link tetap utuh). Bila retrieval tidak menemukan apa pun (mis.
     * index belum terisi), urutan asli dipertahankan.
     *
     * @param  array<int, array<string, mixed>>  $references
     * @return array{references: array<int, array<string, mixed>>, matched: int}
     */
    private function pickRelevantReferences(int $userId, string $query, array $references, RetrievalService $retrieval): array
    {
        $byId = [];
        foreach ($references as $i => $r) {
            $id = trim((string) ($r['id'] ?? ''));
            if ($id !== '') {
                $byId[$id] = $i;
            }
        }

        // Semua referensi tanpa id tidak bisa dipetakan: lempar ke awal daftar
        // agar tetap ikut terkirim bila hasil retrieval tidak penuh.
        $withId = array_values(array_filter($references, fn ($r) => trim((string) ($r['id'] ?? '')) !== ''));
        $withoutId = array_values(array_filter($references, fn ($r) => trim((string) ($r['id'] ?? '')) === ''));

        $hits = $retrieval->searchTopReferences($userId, $query, 8);
        if ($hits === []) {
            return ['references' => $references, 'matched' => 0];
        }

        $picked = [];
        $pickedIds = [];
        foreach ($hits as $hit) {
            $id = $hit['ref_id'];
            if (isset($byId[$id]) && ! in_array($id, $pickedIds, true)) {
                $picked[] = $references[$byId[$id]];
                $pickedIds[] = $id;
            }
        }

        $matched = count($picked);

        // Isi sisa slot dengan referensi lain (urutan asli) supaya AI tetap
        // punya konteks pustaka yang cukup bila relevan sedikit.
        foreach ($withId as $r) {
            if (count($picked) >= 8) {
                break;
            }
            $id = trim((string) ($r['id'] ?? ''));
            if (! in_array($id, $pickedIds, true)) {
                $picked[] = $r;
                $pickedIds[] = $id;
            }
        }

        foreach ($withoutId as $r) {
            if (count($picked) >= 8) {
                break;
            }
            $picked[] = $r;
        }

        return ['references' => $picked, 'matched' => $matched];
    }

    /**
     * Ambil N pesan history terbaru dan potong tiap pesan ke batas aman.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array<int, array<string, string>>
     */
    private function trimHistory(array $history): array
    {
        $turns = array_values(array_filter(
            $history,
            fn ($t) => is_array($t)
                && in_array((string) ($t['role'] ?? ''), ['user', 'assistant'], true)
                && trim((string) ($t['content'] ?? '')) !== '',
        ));

        $turns = array_slice($turns, -self::MAX_HISTORY_TURNS);

        return array_values(array_map(
            fn (array $turn) => [
                'role' => (string) $turn['role'],
                'content' => mb_substr(trim((string) $turn['content']), 0, self::MAX_HISTORY_CHARS),
            ],
            $turns,
        ));
    }

    /**
     * Label format dokumen untuk prompt user.
     */
    private function formatLabel(string $format): string
    {
        return match ($format) {
            'skripsi' => 'Skripsi',
            'tesis' => 'Tesis',
            'disertasi' => 'Disertasi',
            'makalah' => 'Makalah',
            'jurnal' => 'Jurnal',
            'laporan' => 'Laporan',
            'proposal' => 'Proposal',
            'esai' => 'Esai',
            default => 'Dokumen Akademik',
        };
    }

    /**
     * Garis besar struktur baku tiap format (untuk memandu Agent AI Canvas).
     */
    private function documentOutline(string $format): string
    {
        return match ($format) {
            'skripsi' => implode("\n", [
                'Bagian Awal (blok: cover, abstract, toc)',
                '# PENDAHULUAN',
                '## Latar Belakang',
                '## Rumusan Masalah',
                '## Tujuan Penelitian',
                '# KAJIAN PUSTAKA',
                '## Landasan Teori',
                '# METODOLOGI PENELITIAN',
                '## Jenis Penelitian',
                '## Teknik Pengumpulan Data',
                '# HASIL DAN PEMBAHASAN',
                '## Hasil',
                '## Pembahasan',
                '# KESIMPULAN DAN SARAN',
                '## Kesimpulan',
                '## Saran',
                'Daftar Pustaka (blok: references)',
            ]),
            'tesis' => implode("\n", [
                'Bagian Awal (blok: cover, abstract, toc)',
                '# PENDAHULUAN',
                '## Latar Belakang',
                '## Rumusan Masalah',
                '## Tujuan dan Manfaat',
                '# KAJIAN PUSTAKA',
                '## Landasan Teori',
                '# METODOLOGI PENELITIAN',
                '## Desain Penelitian',
                '## Teknik Analisis Data',
                '# HASIL DAN PEMBAHASAN',
                '# KESIMPULAN DAN SARAN',
                'Daftar Pustaka (blok: references)',
            ]),
            'disertasi' => implode("\n", [
                'Bagian Awal (blok: cover, abstract, toc)',
                '# PENDAHULUAN',
                '## Latar Belakang',
                '## Rumusan Masalah',
                '## Tujuan dan Kontribusi',
                '# KAJIAN PUSTAKA',
                '# KERANGKA KONSEPTUAL',
                '# METODOLOGI PENELITIAN',
                '# HASIL DAN PEMBAHASAN',
                '# KESIMPULAN DAN SARAN',
                'Daftar Pustaka (blok: references)',
            ]),
            'makalah' => implode("\n", [
                '# Judul Makalah',
                '## Abstrak',
                '# Pendahuluan',
                '# Pembahasan',
                '# Kesimpulan',
                'Daftar Pustaka (blok: references)',
            ]),
            'jurnal' => implode("\n", [
                '# Judul Jurnal',
                '## Abstrak dan Kata Kunci',
                '# Pendahuluan',
                '# Metode Penelitian',
                '# Hasil dan Pembahasan',
                '# Kesimpulan',
                'Daftar Pustaka (blok: references)',
            ]),
            'laporan' => implode("\n", [
                '# Halaman Judul',
                '## Ringkasan',
                '# Pendahuluan',
                '# Isi Laporan',
                '# Kesimpulan dan Saran',
            ]),
            'proposal' => implode("\n", [
                '# Pendahuluan',
                '## Latar Belakang',
                '## Rumusan Masalah',
                '## Tujuan dan Manfaat',
                '# Tinjauan Pustaka',
                '# Metode Pelaksanaan',
                '# Penutup',
                'Daftar Pustaka (blok: references)',
            ]),
            'esai' => implode("\n", [
                '# Pendahuluan (tesis utama)',
                '# Isi / Pembahasan (argumen dan bukti)',
                '# Kesimpulan',
                'Daftar Pustaka (blok: references)',
            ]),
            default => '',
        };
    }
}
