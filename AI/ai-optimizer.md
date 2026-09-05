# AI Optimizer — Turnitin & Plagiarism

## Peran
Menurunkan deteksi AI (Turnitin AI) dan kemiripan (plagiarism) pada teks user,
serta membantu parafrase dan perbaikan kalimat.

## Tujuan
Membuat teks lebih "manusiawi" dan orisinal tanpa mengubah makna akademik, dengan
target kemiripan Turnitin **di bawah 20%**.

## Dua fitur & tarif kredit

| Fitur | Cakupan baca | Kredit per generate |
| --- | --- | --- |
| Turnitin AI Optimizer | Membaca **seluruh dokumen** (semua halaman) | **20 kredit** |
| Plagiarism Optimizer | Teks/blok yang dicek | **1 kredit** |

- Turnitin membaca semua halaman → biaya 20 kredit.
- Plagiarism sekali generate → 1 kredit (bisa berkali-kali).
- Tarif bersifat **dinamis**: bisa diubah super-admin lewat halaman "Pengaturan Kredit"
  (default di `config/credits.php` + `utils/creditPricing.js`).

## Lokasi kode
- `resources/js/apps/project/index.vue` — `openPlagiarismCheck()`, `openTurnitinOptimizer()`.
- `resources/js/apps/project/components/PlagiarismModal.vue` & `TurnitinModal.vue`.
- `resources/js/utils/creditPricing.js` — tarif kredit terpusat (`ai_plagiarism`, `ai_turnitin`).
- Fitur pemasaran "Turnitin AI Optimizer" & "Plagiarism Optimizer" juga disebut di
  `resources/js/pages/HomePage.vue`.

## Perilaku saat ini
- `openPlagiarismCheck()` memotong kredit lalu menampilkan modal hasil screening.
- Hasil/parafrase belum terhubung LLM nyata (masih dummy).

## Perilaku yang diharapkan
- **Turnitin AI Optimizer** (`reason: 'turnitin_optimize'`, 20 kredit):
  baca seluruh isi canvas, identifikasi kalimat yang berpotensi terdeteksi sebagai
  tulisan AI, lalu beri saran/tulis ulang agar lebih natural.
- **Plagiarism Optimizer** (`reason: 'plagiarism_check'`, 1 kredit):
  cek kemiripan teks terhadap sumber, tampilkan bagian yang mirip + saran parafrase,
  target < 20%.

## Integrasi LLM (ketika tersedia)
- Potong kredit via `/api/wallet/spend` **sebelum** memanggil LLM.
- Turnitin: kirim seluruh teks dokumen (semua blok teks).
- Plagiarism: kirim teks blok/halaman yang dicek.

## Batasan
- Wajib potong kredit sesuai tarif di atas sebelum proses.
- Hasil optimasi harus tetap menjaga sitasi/rujukan.
- Jangan klaim "lolos Turnitin" secara absolut; sampaikan sebagai bantuan penyuntingan.
