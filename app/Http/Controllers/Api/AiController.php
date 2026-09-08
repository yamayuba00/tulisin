<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeepSeek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AiController extends Controller
{
    /**
     * Proxy percakapan AI ke DeepSeek memakai system prompt sesuai agent.
     */
    public function generate(Request $request): Response
    {
        if (! $request->user()->hasActiveSubscription()) {
            return response()->json(['error' => 'Fitur AI memerlukan langganan aktif.'], 402);
        }

        $data = $request->validate([
            'agent' => ['required', 'string', 'in:canvas,copilot,turnitin,plagiarism'],
            'message' => ['required', 'string'],
            'context' => ['nullable', 'string'],
            'uuid' => ['nullable', 'string', 'max:64'],
            'format' => ['nullable', 'string', 'in:skripsi,tesis,disertasi,makalah,jurnal,laporan,proposal,esai'],
            'blockTypes' => ['nullable', 'array'],
            'blockTypes.*' => ['string'],
            'history' => ['nullable', 'array'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string'],
        ]);

        $agent = (string) $data['agent'];
        $format = (string) ($data['format'] ?? '');
        $blockTypes = array_values(array_filter(array_map(
            fn ($t) => is_string($t) ? trim($t) : '',
            $data['blockTypes'] ?? [],
        )));
        $system = $this->systemPrompt($agent);
        $user = $this->buildUserPrompt(
            $agent,
            (string) $data['message'],
            (string) ($data['context'] ?? ''),
            (string) ($data['uuid'] ?? ''),
            $format,
            $blockTypes,
        );

        // Mode JSON untuk agent yang membutuhkan keluaran terstruktur.
        $json = in_array($agent, ['plagiarism', 'turnitin'], true);

        $history = $agent === 'canvas'
            ? array_values(array_map(
                fn (array $turn) => [
                    'role' => (string) $turn['role'],
                    'content' => (string) $turn['content'],
                ],
                $data['history'] ?? [],
            ))
            : [];

        // Temperature per agent: canvas lebih kreatif, sisanya deterministik
        // (0.4) agar minim halusinasi & lebih akurat untuk penulisan/plagiarism.
        $temperature = match ($agent) {
            'canvas' => 0.7,
            default => 0.4,
        };

        // Streaming hanya untuk agent teks bebas (canvas/copilot). Agent JSON
        // (plagiarism/turnitin) tetap dibuffer agar hasilnya bisa di-parse utuh.
        if ($request->boolean('stream') && ! $json) {
            return $this->streamReply($system, $user, $temperature, $history);
        }

        $reply = app(DeepSeek::class)->chat($system, $user, $json, $temperature, $history);

        if ($reply === null) {
            return response()->json(['error' => 'Gagal menghubungi AI. Coba lagi.'], 502);
        }

        return response()->json(['reply' => $reply]);
    }

    /**
     * Kirim balasan AI sebagai Server-Sent Events (SSE) agar teks tampil bertahap.
     */
    private function streamReply(string $system, string $user, float $temperature, array $history): Response
    {
        return response()->stream(function () use ($system, $user, $temperature, $history) {
            $full = app(DeepSeek::class)->stream($system, $user, false, $temperature, $history, function (string $delta): void {
                echo 'data: '.json_encode(['delta' => $delta])."\n\n";
                @ob_flush();
                @flush();
            });

            if ($full === null) {
                echo 'data: '.json_encode(['error' => 'Gagal menghubungi AI. Coba lagi.'])."\n\n";
            } else {
                echo 'data: '.json_encode(['done' => true])."\n\n";
            }
            @ob_flush();
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Susun prompt user: UUID canvas + konteks + instruksi user.
     */
    private function buildUserPrompt(string $agent, string $message, string $context, string $uuid, string $format = '', array $blockTypes = []): string
    {
        $parts = [];

        if ($uuid !== '') {
            $parts[] = "UUID canvas: {$uuid}";
        }

        if ($format !== '' && $agent === 'canvas') {
            $parts[] = "Format target dokumen: {$this->formatLabel($format)}";
            $parts[] = "Struktur baku yang harus diikuti:\n{$this->documentOutline($format)}";
        }

        if ($agent === 'canvas' && $blockTypes !== []) {
            $parts[] = 'Jenis blok canvas yang tersedia: '.implode(', ', $blockTypes);
        }

        if ($context !== '') {
            $label = match ($agent) {
                'plagiarism' => 'Teks target',
                'copilot' => 'Isi halaman aktif',
                default => 'Isi canvas',
            };
            $parts[] = "{$label}:\n{$context}";
        }

        $parts[] = "Instruksi user:\n{$message}";

        return implode("\n\n", $parts);
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
- Tabel markdown dengan `|` = Tabel
- `![keterangan](url)` = Gambar
- `---` = Pembatas
- fenced code ``` ... ``` = Kode (hanya jika user memang butuh blok kode)

Daftar Pustaka:
- JANGAN mengarang daftar pustaka atau sitasi. Daftar pustaka otomatis diisi dari sitasi yang ada di Workspace. Jika user meminta daftar pustaka, arahkan ke blok "Daftar Pustaka"; bila belum ada sitasi, biarkan kosong.

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
