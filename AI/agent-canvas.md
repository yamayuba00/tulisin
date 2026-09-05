# Agent AI — Canvas (Editor Dokumen)

## Peran
Agent AI yang diakses dari tombol **Agent AI di header builder**. Berbeda dari
[agent-project.md](agent-project.md) yang membuat project baru, agent ini bekerja
**di dalam canvas yang sedang dibuka** untuk membantu user memenuhi kebutuhan
blok-blok dokumen.

## Cara akses
- Tombol "Agent AI" di `resources/js/apps/project/components/HeaderBuilder.vue`
  membuka **modal** (bukan pindah halaman). User tetap berada di canvas builder.
- Modal menampilkan percakapan (chat) dan daftar aksi cepat terhadap blok.

## Tujuan
Memenuhi kebutuhan blok pada dokumen aktif: menambah, menyunting, menyusun ulang,
atau melengkapi isi blok sesuai instruksi user, tanpa keluar dari canvas.

## Scope & keamanan (WAJIB)
1. **Tidak boleh lepas dari canvas sendiri.** Agent hanya boleh membaca/menulis
   project yang sedang dibuka (`localStorage['tulisin:project:{id}']` dengan `id`
   dari query `?builder=`), tidak boleh membuka project/route lain.
2. **Tidak boleh membaca canvas orang lain.** Agent hanya melihat data milik user
   yang sedang login dan project aktif. Tidak ada akses ke `tulisin:projects`
   milik user lain atau endpoint lintas-user.
3. Konteks yang dikirim ke backend hanya: judul, jenis dokumen, daftar bab, dan
   blok-blok pada canvas aktif. Tidak menyertakan data project lain.

## Perilaku yang dapat dilakukan
- Membaca struktur canvas aktif (daftar blok, urutan, tipe, isi).
- Menambah blok baru (paragraf, heading, tabel, gambar, dsb.) sesuai permintaan.
- Menyunting isi blok yang sudah ada (tulis ulang, ringkas, kembangkan).
- Menyusun ulang / menghapus blok berdasarkan instruksi user.
- Menjawab pertanyaan seputar isi dokumen aktif.

## Input / konteks
- `projectId` aktif dari query `?builder=`.
- Ringkasan canvas: `canvasSummary` di `resources/js/apps/project/index.vue`.
- Instruksi bebas dari user di modal.

## Batasan
- Setiap perubahan blok harus diterapkan lewat state canvas yang ada
  (`canvasBlocks`), bukan menimpa `localStorage` secara langsung.
- Tidak boleh mengubah project lain meski `id`-nya diketahui.
- Jika kredit diberlakukan, potong via `POST /api/wallet/spend` sebelum eksekusi
  (reason sesuai fitur), bukan langsung dari frontend.
- Hasil harus bisa diedit/dibatalkan user (jangan menimpa tanpa konfirmasi).
