# AI Academic Co-Pilot — Asisten Penulisan (Sidebar Kanan)

## Peran
Asisten AI di **sidebar kanan** builder yang menemani user saat menulis dan
menyunting isi dokumen. Berbeda dari Agent AI Canvas (modal header) yang bekerja
di tingkat dokumen; asisten ini bekerja **per halaman** — membaca isi halaman yang
sedang difokuskan/dibuka, bukan seluruh canvas sekaligus.

## Tujuan
Membantu user menghasilkan dan memperbaiki konten akademik sesuai konteks halaman
yang sedang dibaca.

## Lokasi kode
- `resources/js/apps/project/index.vue` — `generateBlockContent()`,
  `insertGeneratedContent()`, `canvasSummary`.
- `resources/js/apps/project/components/InspectorPanel.vue` — tab AI, input/output
  (`aiGenInput`, `aiGenOutput`), prompt `blockAiPrompts`.
- `resources/js/components/FloatingAI.vue` — widget percakapan asisten.
- Biaya generate: `generate = 5 kredit` (lihat konstanta `AI_COST`).

## Perilaku per halaman
- **Asisten umum** (blok tidak dipilih): membaca konteks **halaman aktif**
  (`currentChapter` / halaman yang sedang difokuskan), lalu menjawab pertanyaan
  user terkait halaman tersebut.
- **Generate per blok** (blok dipilih): membaca isi **halaman tempat blok berada**
  dan menulis hasilnya ke blok tersebut.

## Perilaku saat ini
- `generateBlockContent()` membaca `aiGenInput` dan menghasilkan **draf contoh**
  (belum LLM nyata). Hasil asli menunggu backend AI aktif.
- Konten hasil dimasukkan ke blok terpilih lewat `insertGeneratedContent()`.

## Integrasi LLM (ketika tersedia)
- Ganti body `generateBlockContent()` dengan request ke endpoint AI backend,
  kirim konteks **halaman aktif** (bukan seluruh dokumen): judul project, jenis
  dokumen, judul bab/halaman, dan isi blok pada halaman tersebut.
- Tetap potong kredit via `/api/wallet/spend` (`reason: 'ai_generate'`) **sebelum**
  memanggil LLM.

## Batasan
- Wajib potong kredit (`reason: 'ai_generate'`) sebelum generate.
- Konteks hanya halaman aktif; jangan kirim isi seluruh canvas untuk asisten ini.
- Jangan panggil LLM berulang tanpa jeda; debounce input user.
- Hasil harus bisa diedit user (tidak menimpa blok tanpa konfirmasi).
