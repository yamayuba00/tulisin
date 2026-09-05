<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeepSeek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
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
            'message' => ['required', 'string'],
            'context' => ['nullable', 'string'],
            'uuid' => ['nullable', 'string', 'max:64'],
            'format' => ['nullable', 'string', 'in:skripsi,tesis,disertasi,makalah,jurnal,laporan,proposal,esai'],
        ]);

        $agent = (string) $data['agent'];
        $format = (string) ($data['format'] ?? '');
        $system = $this->systemPrompt($agent);
        $user = $this->buildUserPrompt(
            $agent,
            (string) $data['message'],
            (string) ($data['context'] ?? ''),
            (string) ($data['uuid'] ?? ''),
            $format,
        );

        // Mode JSON untuk agent yang membutuhkan keluaran terstruktur.
        $json = in_array($agent, ['plagiarism', 'turnitin'], true);

        $reply = app(DeepSeek::class)->chat($system, $user, $json);

        if ($reply === null) {
            return response()->json(['error' => 'Gagal menghubungi AI. Coba lagi.'], 502);
        }

        return response()->json(['reply' => $reply]);
    }

    /**
     * Susun prompt user: UUID canvas + konteks + instruksi user.
     */
    private function buildUserPrompt(string $agent, string $message, string $context, string $uuid, string $format = ''): string
    {
        $parts = [];

        if ($uuid !== '') {
            $parts[] = "UUID canvas: {$uuid}";
        }

        if ($format !== '' && $agent === 'canvas') {
            $parts[] = "Format target dokumen: {$this->formatLabel($format)}";
            $parts[] = "Struktur baku yang harus diikuti:\n{$this->documentOutline($format)}";
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
Anda adalah Agent AI Canvas, asisten ahli penyusun dokumen akademik yang bekerja langsung di dalam satu canvas milik user.

Tugas Anda:
1. Baca seluruh isi canvas yang diberikan, lalu bantu user menyusun, melengkapi, menyunting, atau menata blok-blok dokumen sesuai kebutuhan.
2. Jika canvas masih kosong, tawarkan langkah awal secara proaktif (kerangka, cover, abstrak, daftar isi, bab, hingga daftar pustaka).
3. Jika diberikan "Format target dokumen" beserta strukturnya, susun jawaban persis mengikuti struktur baku tersebut (gunakan heading markdown untuk judul bab/sub-bab) agar hasilnya rapi dan bisa langsung dipakai.
4. Jawab dengan bahasa Indonesia yang jelas dan langsung bisa dipakai.

Batasan:
- Hanya bekerja pada canvas milik user tersebut. Jangan membaca/menyebut/mengubah canvas lain.
- Jangan menambahkan konten yang tidak relevan dengan kebutuhan dokumen.
- Jangan menimpa/menghapus isi blok tanpa konfirmasi user.
- Konten harus akademik, netral, dan bebas plagiarisme.
PROMPT,
            'copilot' => <<<'PROMPT'
Anda adalah AI Academic Co-Pilot, asisten penulisan akademik yang menemani user menulis di halaman aktif dokumennya.

Tugas Anda:
1. Baca isi halaman aktif yang diberikan, lalu bantu menulis atau menyunting konten pada halaman tersebut.
2. Jawab pertanyaan seputar halaman ini: saran paragraf, ringkasan, pengembangan kalimat, atau perbaikan tata bahasa.

Batasan:
- Hanya gunakan konteks halaman aktif. Jangan membaca/menjawab dari halaman lain kecuali diminta.
- Jangan menimpa isi blok tanpa konfirmasi user.
- Konten harus akademik dan mengikuti gaya penulisan dokumen.
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
                'Bagian Awal: Cover, Abstrak, Daftar Isi',
                'BAB I Pendahuluan: Latar Belakang, Rumusan Masalah, Tujuan Penelitian',
                'BAB II Kajian Pustaka: Landasan Teori',
                'BAB III Metodologi Penelitian: Jenis Penelitian, Teknik Pengumpulan Data',
                'BAB IV Hasil dan Pembahasan: Hasil, Pembahasan',
                'BAB V Kesimpulan dan Saran',
                'Daftar Pustaka',
            ]),
            'tesis' => implode("\n", [
                'Bagian Awal: Cover, Abstrak, Daftar Isi',
                'BAB I Pendahuluan: Latar Belakang, Rumusan Masalah, Tujuan dan Manfaat',
                'BAB II Kajian Pustaka: Landasan Teori',
                'BAB III Metodologi Penelitian: Desain Penelitian, Teknik Analisis Data',
                'BAB IV Hasil dan Pembahasan',
                'BAB V Kesimpulan dan Saran, Daftar Pustaka',
            ]),
            'disertasi' => implode("\n", [
                'Bagian Awal: Cover, Abstrak, Daftar Isi',
                'BAB I Pendahuluan: Latar Belakang, Rumusan Masalah, Tujuan dan Kontribusi',
                'BAB II Kajian Pustaka',
                'BAB III Kerangka Konseptual',
                'BAB IV Metodologi Penelitian',
                'BAB V Hasil dan Pembahasan',
                'BAB VI Kesimpulan dan Saran, Daftar Pustaka',
            ]),
            'makalah' => implode("\n", [
                'Judul dan Abstrak',
                'Pendahuluan',
                'Pembahasan',
                'Kesimpulan, Daftar Pustaka',
            ]),
            'jurnal' => implode("\n", [
                'Abstrak dan Kata Kunci',
                'Pendahuluan',
                'Metode Penelitian',
                'Hasil dan Pembahasan',
                'Kesimpulan, Daftar Pustaka',
            ]),
            'laporan' => implode("\n", [
                'Halaman Judul dan Ringkasan',
                'Pendahuluan',
                'Isi Laporan',
                'Kesimpulan dan Saran',
            ]),
            'proposal' => implode("\n", [
                'Pendahuluan: Latar Belakang, Rumusan Masalah, Tujuan dan Manfaat',
                'Tinjauan Pustaka',
                'Metode Pelaksanaan',
                'Penutup, Daftar Pustaka',
            ]),
            'esai' => implode("\n", [
                'Pendahuluan (tesis utama)',
                'Isi / Pembahasan (argumen dan bukti)',
                'Kesimpulan, Daftar Pustaka',
            ]),
            default => '',
        };
    }
}
