# Semantic Scholar — Asisten Riset

## Peran
Mencari paper/jurnal referensi akademik untuk mendukung penulisan.

## Tujuan
Menyediakan referensi berkualitas (judul, penulis, tahun, abstrak, URL) dari
Semantic Scholar.

## Lokasi kode
- Backend proxy: `app/Http/Controllers/Api/PaperController.php`.
- Route: `routes/api.php`.
- Frontend: `resources/js/apps/journals/index.vue` (menu "Paper / Journal").

## Perilaku
- Proxy ke `https://api.semanticscholar.org/` dari server (hindari CORS & simpan key).
- Tangani rate limit `429` dengan pesan ramah; dukung `SEMANTIC_SCHOLAR_API_KEY`.

## Batasan
- Jangan memanggil API eksternal langsung dari browser.
- Batasi request (rate limiter Laravel + retry/backoff untuk 429).
