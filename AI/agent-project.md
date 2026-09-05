# Agent AI — Pembuat Project

## Peran
Membantu user membuat **project dokumen baru** secara otomatis dari deskripsi
singkat (judul, jenis dokumen, topik, dan daftar bab).

## Tujuan
Dari input user, hasilkan struktur dokumen yang langsung bisa dibuka di canvas
builder, tanpa user menyusun blok satu per satu.

## Lokasi kode
- Halaman: `resources/js/apps/agent/index.vue` (route `/apps/u/agent`, name `agent`).
- Utilitas pembuat struktur: `resources/js/utils/agentProject.js`.
- Didaftarkan di: `resources/js/router/index.js`, `resources/js/apps/index.vue`
  (sidebar), dan tombol di `resources/js/apps/project/components/HeaderBuilder.vue`.

## Input yang dibutuhkan
- `documentType` — jenis dokumen (Skripsi, Tesis, Makalah, Jurnal, Laporan, Proposal).
- `title` — judul/topik project.
- `description` — deskripsi/tujuan singkat (opsional, dipakai sebagai abstrak).
- `chapters` — daftar bab (opsional, dipisah koma). Jika kosong, pakai default per jenis.

## Perilaku
1. Validasi: `title` wajib (trim, non-kosong).
2. Susun blok dengan urutan: `cover` → `abstract` (isi `description`) → `toc` →
   satu blok `chapter` + `h1` + `paragraph` per bab → `references`.
3. Tulis project ke `localStorage['tulisin:project:{id}']` dan panggil
   `touchProject(id, { name: title, category: documentType, blocks })`.
4. Arahkan ke builder `router.push('/apps/u/project?builder={id}')`.

## Batasan
- **Belum** memotong kredit (kecuali nanti ditentukan user).
- Belum terhubung LLM nyata — struktur dihasilkan secara deterministik dari input.
  Jika nanti ada endpoint LLM, ganti bagian pembuat konten paragraf saja.
- Tidak boleh menimpa project lain; `id` dibuat unik via `crypto.randomUUID()`.

## Struktur bab default
- Skripsi/Tesis: Pendahuluan, Kajian Pustaka, Metodologi, Hasil dan Pembahasan, Kesimpulan dan Saran.
- Makalah/Jurnal: Pendahuluan, Metode, Hasil dan Pembahasan, Kesimpulan.
- Laporan/Proposal: Pendahuluan, Rencana Pelaksanaan, Anggaran, Penutup.
