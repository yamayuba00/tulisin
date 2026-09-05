# Pedoman Umum (wajib dibaca semua agent)

Aturan lintas-fitur yang berlaku untuk seluruh AI/agent di project **Tulisin**.

## Stack
- Laravel 13 + Vue 3 (`<script setup>`), Vite, Tailwind CSS 4, Vue Router.
- Autentikasi: Laravel Sanctum (cookie/session). **Jangan** pakai `localStorage`
  untuk menyimpan token/user.

## Autentikasi & request
- Client HTTP: `resources/js/utils/http.js` (`getJson`, `request`).
- Selalu `credentials: 'include'` dan header `X-XSRF-TOKEN` (diatur otomatis).
- State auth: `resources/js/utils/auth.js` — `useAuth()` memberi `currentUser`,
  `login`, `register`, `logout`, `fetchMe`, `init`.

## Wallet & kredit
- Model: `app/Models/Wallet.php`, `app/Models/CreditTransaction.php`.
- Endpoint (auth): `GET /api/wallet`, `POST /api/wallet/topup`, `POST /api/wallet/spend`.
- Pemotongan kredit **hanya lewat** `POST /api/wallet/spend` dengan body
  `{ credits: int, reason: string }`. `reason` max 40 karakter.
- Jika saldo kurang, server balas `422` dengan `{ error: 'Saldo kredit tidak mencukupi.' }`.
- Alasan (reason) yang sudah dipakai: `topup`, `template_use`, `image_upload`,
  `font_upload`, `affiliate_referral`, `ai_generate`, `plagiarism_check`,
  `download`, `turnitin_optimize`.

## Project & builder
- Data project: `localStorage['tulisin:project:{id}']` (JSON penuh, bisa besar).
- Indeks project: `localStorage['tulisin:projects']` (metadata ringan + pratinjau).
- Utilitas: `resources/js/utils/projectIndex.js`
  (`listProjects`, `touchProject`, `removeProject`, `buildProjectPreview`, `getProjectPreview`).
- Setiap buat/ubah project WAJIB memanggil `touchProject(id, { name, category, lastEdited, blocks })`
  agar muncul di halaman daftar project.
- Template: `resources/js/utils/templates.js` (`TEMPLATES`, `TEMPLATE_COST = 50`,
  `buildProjectPayload`, `buildTemplateBlocks`).

## Struktur blok canvas
Setiap blok punya minimal field:
```
{ uid, type, content, indent, align, width, spacing, fontFamily, fontSize,
  lineHeight, color, caption, captionPosition, showCaption, customNumber, pageTitle }
```
Jenis blok (lihat `blockTypes` di `resources/js/apps/project/index.vue`):
`cover, abstract, toc, listTables, listFigures, references, blankPage, chapter,
h1..h10, formula, paragraph, bullet, number, quote, code, table, image, divider, spacer`.

Jenis yang otomatis "pindah halaman" (force break): cover, abstract, toc,
listTables, listFigures, references, chapter.

## Responsivitas
- Breakpoint Tailwind: `sm` 640, `md` 768, `lg` 1024, `xl` 1280.
- Drawer sidebar/palette/inspector: `fixed` di mobile, `lg/xl:static` di desktop.
- Tombol header builder: sembunyikan label di layar kecil (`hidden sm:inline`),
  pertahankan ikon agar tidak overflow.

## Konvensi
- ID internal: `id` (bigint auto-increment) untuk join; `uuid` publik untuk ditampilkan.
- Bahasa UI/komentar: Indonesia.
- Jangan menambah dependency bila bisa dibuat sendiri.
