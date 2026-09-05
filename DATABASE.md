# 📘 Desain Database — Tulisin

> Dokumen desain skema database (belum berupa migration). Tujuannya sebagai bahan belajar & acuan
> implementasi backend Laravel. Nama tabel/kolom memakai konvensi **snake_case** (Inggris), sedangkan
> penjelasan memakai Bahasa Indonesia.

---

## 1. Ringkasan Sistem

Tulisin adalah platform penulisan dokumen (skripsi, tesis, makalah, jurnal, laporan, dll.) berbasis **Laravel + Vue**.
Database dirancang modular per domain agar mudah berkembang. Domain utamanya:

| # | Domain | Keterangan |
|---|--------|-----------|
| 1 | Pengguna & Auth | Mahasiswa / pengguna umum menyusun dokumen |
| 2 | RBAC (Role & Permission) | Admin, moderator, user, writer, agency, affiliate, partner, advertiser |
| 3 | File & Media | File Manager (gambar) & File Font |
| 4 | Project & Dokumen | Project, blok canvas, versi, publikasi (Lists Project) |
| 5 | Kredit & Wallet | Saldo kredit, riwayat transaksi, paket topup |
| 6 | Payment & Topup | Order topup, pembayaran, verifikasi |
| 7 | Program Dapatkan Kredit | Kirim URL → verifikasi admin → kredit |
| 8 | Affiliate / Referral | Kode referral, komisi, pencairan |
| 9 | Jasa Penulisan / Agency / Olah Data | Katalog jasa, order, chat, revisi, deliverable |
| 10 | Kampus / B2B SaaS | Organisasi, seat anggota, langganan |
| 11 | Iklan & Promo | Campaign iklan, slot, tracking, kupon |
| 12 | Support & Log | Notifikasi, audit log, tiket |
| 13 | Analytics & Monitoring | Traffic (page_views), dashboard super-admin |

---

## 1.1 Rekomendasi Database: PostgreSQL

Dari keseluruhan struktur di atas, database yang paling tepat (powerful) untuk membangun Tulisin adalah **PostgreSQL**.

| Aspek | Kenapa PostgreSQL |
|-------|------------------|
| Blok dokumen & snapshot | Tipe `JSONB` untuk `project_blocks.content`/`meta`, `snapshot`, `features`, `data` — bisa di-query & di-index langsung |
| Full-text search | `tsvector` + ekstensi `pg_trgm` untuk pencarian project, jasa, dan tiket |
| UUID native | Tipe `UUID` bawaan untuk identitas publik (lihat §1.2) |
| Ledger kredit | MVCC + isolation kuat agar `credit_transactions` (append-only) konsisten saat concurrency tinggi |
| Skalabilitas | Lebih baik menangani data relasional besar + banyak kolom JSON |
| Ekstensi | `uuid-ossp`, `pgcrypto`, `pg_trgm` |

> Laravel mendukung penuh PostgreSQL via koneksi `pgsql`. MySQL/MariaDB (default Laragon) tetap bisa dipakai,
> tapi kurang optimal untuk JSONB, full-text search, dan UUID native.

---

## 1.2 Konvensi Primary Key: `id` + `uuid`

Setiap tabel memakai **dua identitas** yang perannya berbeda:

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key **internal** — dipakai untuk JOIN / foreign key antar tabel di balik layar |
| `uuid` | UUID (CHAR(36) UQ) | Identitas **publik** — dipakai di URL, response API, dan dibagikan ke pengguna |

**Kenapa dipisah:**

1. **`id` untuk join di balik layar** — integer auto-increment itu ringan, index-nya cepat, dan TIDAK pernah diekspos keluar.
2. **`uuid` untuk ditampilkan / dibagikan** — nilainya acak sehingga tidak bisa ditebak atau di-enumerasi
   (tidak "ketangkap"/tercapture). Aman dipakai di URL publik (`/project/{uuid}`), API, referral, invoice.
3. **Keamanan & privasi** — penyerang tidak bisa menebak ID berikutnya (`/project/1001` → `/project/1002`),
   sehingga mencegah scraping, enumerasi data, dan kebocoran informasi (mis. jumlah total user/project).

**Implementasi di Laravel:**

- Gunakan trait `HasUuids` atau generate `uuid` di `booted()` dengan `Str::orderedUuid()` agar index tetap terurut.
- `uuid` di-generate otomatis saat record dibuat dan bersifat **immutable** (tidak pernah berubah).
- Routing & resource API memakai `uuid` (mis. `Route::model('project', Project::class, 'uuid')` atau `whereUuid`),
  sedangkan relasi Eloquent internal tetap memakai `id`.

> Catatan: pada auth sementara di `resources/js/utils/auth.js`, `crypto.randomUUID()` yang saat ini dipakai sebagai
> `id` nantinya menjadi nilai kolom `uuid`. Kolom `id` (integer) akan di-generate database secara terpisah.

---

## 1.3 Kepemilikan Data (per pengguna)

Setiap data bisnis punya kolom `user_id` (atau `organization_id` untuk B2B) sebagai pemiliknya, sehingga:

- Query aplikasi **selalu di-scope** ke user yang sedang login (mis. `where('user_id', auth()->id())`).
- Pengguna biasa hanya bisa melihat data miliknya sendiri.
- **Super-admin** (dan role tertentu) memakai global scope / `withoutGlobalScope` untuk melihat & memantau
  seluruh data lintas pengguna: traffic, transaksi, order, submission, revenue.

---

## 2. Role & Hak Akses (RBAC)

Pakai pola RBAC (rekomendasi: package **`spatie/laravel-permission`**). Hak akses dipisah dari `users`
agar satu user bisa punya banyak role (mis. mahasiswa sekaligus affiliate).

**Role & permission bersifat dinamis** — bukan enum hardcoded. Sebagai super-admin kamu bisa:

- Membuat / mengedit / menghapus **role baru** langsung dari dashboard (tanpa ubah kode).
- Menambah **permission baru** saat ada fitur baru (cukup insert baris ke tabel `permissions`).
- Menetapkan permission ke role, dan role ke user, semua lewat UI.
- Data user tetap tersimpan dengan `user_id` (kepemilikan per pengguna), sedangkan super-admin punya
  akses lintas-user untuk memantau traffic, transaksi, dan aktivitas.

### 2.1 Tabel RBAC

| Tabel | Fungsi |
|-------|--------|
| `roles` | Daftar role |
| `permissions` | Daftar permission (izin aksi) |
| `model_has_roles` | Relasi user ↔ role (polymorphic) |
| `role_has_permissions` | Relasi role ↔ permission |
| `model_has_permissions` | Relasi user ↔ permission langsung (override) |

### 2.2 Daftar Role

| Role | Deskripsi | Catatan |
|------|-----------|---------|
| `admin` | Super admin | Akses penuh, kelola semua |
| `moderator` | Admin konten | Verifikasi kredit & project publik |
| `user` | Mahasiswa / pengguna umum | Default saat registrasi |
| `writer` | Penulis jasa | Mengerjakan order jasa penulisan |
| `agency` | Agency skripsi / olah data | Mengelola tim writer & layanan |
| `affiliate` | Referral | Punya kode referral, melihat komisi |
| `partner` | Kampus / perusahaan (B2B) | Mengelola seat anggota organisasi |
| `advertiser` | Pengiklan | Mengelola campaign iklan |

### 2.3 Matriks Hak Akses (ringkas)

| Kemampuan | admin | moderator | user | writer | agency | affiliate | partner | advertiser |
|-----------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Kelola pengguna & role | ✅ | — | — | — | — | — | — | — |
| Verifikasi kredit / project publik | ✅ | ✅ | — | — | — | — | — | — |
| Buat & edit project sendiri | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lihat Lists Project (read-only) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Topup & pakai kredit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Buka jasa penulisan | — | — | — | ✅ | ✅ | — | — | — |
| Kelola anggota agency | — | — | — | — | ✅ | — | — | — |
| Akses dashboard affiliate | — | — | — | — | — | ✅ | — | — |
| Kelola organisasi & seat | — | — | — | — | — | — | ✅ | — |
| Pasang iklan | — | — | — | — | — | — | — | ✅ |

> Catatan: tabel `permissions` sebaiknya berisi izin granular seperti
> `projects.create`, `credits.topup`, `submissions.review`, `ads.manage`, dst.

### 2.4 Katalog Permission (granular — bisa ditambah saat ada fitur baru)

Permission mengikuti pola `{modul}.{aksi}`. Saat fitur baru dibuat, super-admin tinggal menambah baris
permission baru lalu menetapkannya ke role dari dashboard.

| Modul | Permission |
|-------|-----------|
| Pengguna & role | `users.view`, `users.manage`, `roles.manage`, `roles.assign` |
| Project | `projects.create`, `projects.update`, `projects.delete`, `projects.publish`, `projects.view_all` |
| Media & font | `media.upload`, `media.delete`, `fonts.manage` |
| Kredit & wallet | `credits.topup`, `credits.view`, `credits.adjust` |
| Topup & payment | `payments.view`, `payments.refund` |
| Program kredit | `submissions.create`, `submissions.review` |
| Affiliate | `affiliates.view`, `affiliates.approve`, `affiliates.payout` |
| Jasa penulisan | `services.manage`, `services.order`, `orders.review` |
| Kampus / B2B | `organizations.manage`, `subscriptions.manage` |
| Iklan & promo | `ads.manage`, `coupons.manage` |
| Support & log | `tickets.manage`, `audit.view` |
| Monitoring | `analytics.view` (traffic & transaksi) |

---

## 3. Struktur Tabel

Konvensi:
- `PK` = primary key, `FK` = foreign key, `UQ` = unique, `IDX` = index, `NULL` = nullable.
- Seluruh tabel memakai `id BIGINT UNSIGNED AUTO_INCREMENT` sebagai PK internal (kecuali dinyatakan lain).
- Seluruh tabel juga memakai `uuid UUID UQ` sebagai identitas publik (lihat §1.2). `uuid` dipakai di URL/API, `id` dipakai untuk JOIN antar tabel.
- Hampir semua tabel punya `created_at` & `updated_at`; tabel yang perlu penghapusan lunak punya `deleted_at`.

---

### 3.1 Auth & Pengguna

#### `users`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| uuid | UUID UQ | Identitas publik (URL/API) |
| name | VARCHAR(120) | Nama lengkap |
| email | VARCHAR(191) UQ | Email login |
| email_verified_at | TIMESTAMP NULL | Kapan email terverifikasi |
| password | VARCHAR(255) | Hash password (bcrypt) |
| phone | VARCHAR(20) NULL | Nomor HP/WA |
| avatar | VARCHAR(255) NULL | Path avatar |
| status | ENUM('active','suspended','banned') | Status akun |
| last_login_at | TIMESTAMP NULL | Login terakhir |
| created_at, updated_at | TIMESTAMP | — |

#### `user_profiles` (1:1 — mahasiswa)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED UQ FK→users | Pemilik profil |
| university | VARCHAR(191) NULL | Nama kampus |
| major | VARCHAR(191) NULL | Jurusan/prodi |
| nim | VARCHAR(40) NULL | Nomor induk mahasiswa |
| degree | VARCHAR(20) NULL | Jenjang: S1/S2/S3/D3 |
| created_at, updated_at | TIMESTAMP | — |

#### `writer_profiles` (1:1 — penulis/agency)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED UQ FK→users | Pemilik profil |
| agency_name | VARCHAR(191) NULL | Nama agency (jika agency) |
| bio | TEXT NULL | Deskripsi singkat |
| specialties | JSON NULL | Keahlian (array kategori) |
| rating_avg | DECIMAL(3,2) DEFAULT 0 | Rata-rata rating |
| completed_orders | INT DEFAULT 0 | Jumlah order selesai |
| is_verified | TINYINT(1) DEFAULT 0 | Terverifikasi admin |
| created_at, updated_at | TIMESTAMP | — |

#### `advertiser_profiles` (1:1 — pengiklan)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED UQ FK→users | Pemilik profil |
| company_name | VARCHAR(191) | Nama perusahaan |
| tax_id | VARCHAR(64) NULL | NPWP (untuk invoice) |
| billing_email | VARCHAR(191) NULL | Email tagihan |
| created_at, updated_at | TIMESTAMP | — |

> Laravel bawaan: `password_reset_tokens` dan `sessions` tetap dipakai (tidak dijelaskan ulang di sini).

---

### 3.2 RBAC

#### `roles`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(191) UQ | Nama role (contoh: `admin`) |
| guard_name | VARCHAR(191) | Guard (default `web`) |
| created_at, updated_at | TIMESTAMP | — |

#### `permissions`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(191) UQ | Nama permission (contoh: `submissions.review`) |
| guard_name | VARCHAR(191) | Guard |
| created_at, updated_at | TIMESTAMP | — |

#### `model_has_roles`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| role_id | BIGINT UNSIGNED FK→roles | Role |
| model_type | VARCHAR(191) | Tipe model (default `App\Models\User`) |
| model_id | BIGINT UNSIGNED | ID user |

#### `role_has_permissions`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| permission_id | BIGINT UNSIGNED FK→permissions | Permission |
| role_id | BIGINT UNSIGNED FK→roles | Role |

#### `model_has_permissions`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| permission_id | BIGINT UNSIGNED FK→permissions | Permission |
| model_type | VARCHAR(191) | Tipe model |
| model_id | BIGINT UNSIGNED | ID user |

---

### 3.3 File & Media

#### `media` (File Manager — gambar)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED FK→users | Pemilik file |
| name | VARCHAR(191) | Nama file asli |
| mime | VARCHAR(100) | Tipe MIME (contoh: `image/png`) |
| size | INT UNSIGNED | Ukuran (byte) |
| path | VARCHAR(255) | Path/URL penyimpanan (storage) |
| created_at, updated_at | TIMESTAMP | — |

#### `fonts` (File Font)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED NULL FK→users | NULL = font global |
| family | VARCHAR(191) | Nama family font |
| format | ENUM('truetype','opentype','woff','woff2') | Format font |
| path | VARCHAR(255) | Path file font |
| created_at, updated_at | TIMESTAMP | — |

---

### 3.4 Project & Dokumen

#### `projects`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| uuid | UUID UQ | Identitas publik (URL /project/{uuid}) |
| user_id | BIGINT UNSIGNED FK→users IDX | Pemilik project |
| title | VARCHAR(191) | Judul project |
| category | VARCHAR(40) IDX | Kategori (Skripsi, Makalah, Jurnal, dst.) |
| description | TEXT NULL | Deskripsi singkat |
| format | VARCHAR(10) DEFAULT 'A4' | Ukuran kertas (A4/A5) |
| orientation | ENUM('portrait','landscape') DEFAULT 'portrait' | Orientasi |
| status | ENUM('draft','published','archived') DEFAULT 'draft' | Status project |
| is_public | TINYINT(1) DEFAULT 0 | Tampil di Lists Project |
| published_at | TIMESTAMP NULL | Kapan dipublikasikan |
| deleted_at | TIMESTAMP NULL | Soft delete |
| created_at, updated_at | TIMESTAMP | — |

#### `project_blocks` (blok canvas — pengganti localStorage `tulisin:project:{id}`)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| project_id | BIGINT UNSIGNED FK→projects IDX | Project |
| type | VARCHAR(30) | Jenis blok (heading, paragraph, image, code, dst.) |
| content | LONGTEXT NULL | Isi blok (teks/JSON) |
| meta | JSON NULL | Style per blok (font, warna, line-height) |
| position | INT UNSIGNED | Urutan blok |
| uid | VARCHAR(64) UQ | ID unik (untuk sinkronisasi frontend) |
| created_at, updated_at | TIMESTAMP | — |

#### `project_versions` (riwayat/snapshot)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| project_id | BIGINT UNSIGNED FK→projects | Project |
| version_no | INT UNSIGNED | Nomor versi |
| snapshot | JSON | Snapshot blok/setting |
| created_by | BIGINT UNSIGNED FK→users | Siapa yang menyimpan |
| created_at | TIMESTAMP | — |

#### `project_publish_requests` (moderasi agar tampil di Lists Project)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| project_id | BIGINT UNSIGNED FK→projects | Project |
| status | ENUM('pending','approved','rejected') DEFAULT 'pending' | Status moderasi |
| reviewed_by | BIGINT UNSIGNED NULL FK→users | Admin yang mereview |
| review_note | TEXT NULL | Catatan admin |
| reviewed_at | TIMESTAMP NULL | Waktu review |
| created_at, updated_at | TIMESTAMP | — |

#### `project_collaborators` (opsional — untuk agency)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| project_id | BIGINT UNSIGNED FK→projects | Project |
| user_id | BIGINT UNSIGNED FK→users | Kolaborator |
| role | ENUM('owner','editor','viewer') DEFAULT 'viewer' | Hak di project |

---

### 3.5 Kredit, Wallet & Topup

#### `wallets` (saldo kredit per user)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED UQ FK→users | Pemilik wallet |
| balance | BIGINT DEFAULT 0 | Saldo kredit tersedia |
| on_hold | BIGINT DEFAULT 0 | Kredit tertahan (pending) |
| created_at, updated_at | TIMESTAMP | — |

#### `credit_transactions` (buku besar / ledger)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| wallet_id | BIGINT UNSIGNED FK→wallets IDX | Wallet |
| user_id | BIGINT UNSIGNED FK→users IDX | Denormalisasi agar mudah query |
| type | ENUM('credit','debit') | Kredit masuk / keluar |
| amount | BIGINT | Jumlah kredit (positif) |
| balance_after | BIGINT | Saldo setelah transaksi |
| reason | ENUM('topup','usage','affiliate_commission','referral_bonus','submission_reward','refund','admin_adjustment') | Alasan |
| reference_type | VARCHAR(191) NULL | Polymorphic type |
| reference_id | BIGINT UNSIGNED NULL | Polymorphic id |
| created_at | TIMESTAMP | — |

#### `credit_packages` (paket topup)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(191) | Nama paket |
| credits | INT UNSIGNED | Jumlah kredit |
| bonus_credits | INT UNSIGNED DEFAULT 0 | Bonus kredit |
| price | DECIMAL(15,2) | Harga (IDR) |
| is_active | TINYINT(1) DEFAULT 1 | Aktif/tidak |
| created_at, updated_at | TIMESTAMP | — |

---

### 3.6 Payment & Topup

#### `payments` (transaksi pembayaran)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| uuid | UUID UQ | Identitas publik (URL pembayaran) |
| user_id | BIGINT UNSIGNED FK→users IDX | Pembayar |
| invoice_number | VARCHAR(40) UQ | Nomor invoice |
| amount | DECIMAL(15,2) | Nominal (IDR) |
| method | VARCHAR(30) | Midtrans/Xendit/Stripe/bank/ewallet |
| provider | VARCHAR(30) NULL | Nama gateway |
| provider_ref | VARCHAR(191) NULL | ID transaksi dari gateway |
| status | ENUM('pending','paid','failed','expired','refunded') | Status |
| paid_at | TIMESTAMP NULL | Waktu bayar |
| created_at, updated_at | TIMESTAMP | — |

#### `topup_orders` (order topup kredit)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED FK→users | Pembeli |
| payment_id | BIGINT UNSIGNED NULL FK→payments | Pembayaran |
| credit_package_id | BIGINT UNSIGNED NULL FK→credit_packages | Paket (nullable jika custom) |
| credits | INT UNSIGNED | Kredit yang dibeli |
| amount | DECIMAL(15,2) | Nominal |
| status | ENUM('pending','paid','cancelled') DEFAULT 'pending' | Status |
| created_at, updated_at | TIMESTAMP | — |

#### `credit_submissions` (Program Dapatkan Kredit — kirim URL)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED FK→users IDX | Pengirim |
| url | VARCHAR(255) | URL konten |
| notes | TEXT NULL | Catatan |
| status | ENUM('pending','approved','rejected') DEFAULT 'pending' | Status verifikasi |
| credits_awarded | INT DEFAULT 0 | Kredit yang diberikan jika disetujui |
| reviewed_by | BIGINT UNSIGNED NULL FK→users | Admin yang mereview |
| review_note | TEXT NULL | Catatan admin |
| reviewed_at | TIMESTAMP NULL | Waktu review |
| created_at, updated_at | TIMESTAMP | — |

---

### 3.7 Affiliate / Referral

#### `referral_codes`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| uuid | UUID UQ | Identitas publik (link referral) |
| user_id | BIGINT UNSIGNED UQ FK→users | Pemilik kode |
| code | VARCHAR(40) UQ | Kode referral unik |
| is_active | TINYINT(1) DEFAULT 1 | Aktif/tidak |
| created_at, updated_at | TIMESTAMP | — |

#### `referrals`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| referrer_id | BIGINT UNSIGNED FK→users | Pengajak |
| referred_user_id | BIGINT UNSIGNED UQ FK→users | Yang diajak |
| referral_code_id | BIGINT UNSIGNED NULL FK→referral_codes | Kode yang dipakai |
| status | ENUM('registered','qualified') DEFAULT 'registered' | Terdaftar / memenuhi syarat |
| created_at, updated_at | TIMESTAMP | — |

#### `affiliate_commissions`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| affiliate_id | BIGINT UNSIGNED FK→users | Affiliate yang dapat komisi |
| referral_id | BIGINT UNSIGNED NULL FK→referrals | Referensi referral |
| reference_type | VARCHAR(191) NULL | Sumber (topup_order/subscription) |
| reference_id | BIGINT UNSIGNED NULL | ID sumber |
| amount | DECIMAL(15,2) | Nominal komisi (IDR) |
| rate | DECIMAL(5,2) | Persentase komisi |
| status | ENUM('pending','approved','paid','cancelled') DEFAULT 'pending' | Status |
| created_at, updated_at | TIMESTAMP | — |

#### `affiliate_payouts` (pencairan komisi)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| affiliate_id | BIGINT UNSIGNED FK→users | Affiliate |
| amount | DECIMAL(15,2) | Nominal pencairan |
| method | VARCHAR(30) | Bank/ewallet |
| account_detail | VARCHAR(191) | Nomor rekening/ewallet |
| status | ENUM('pending','paid','rejected') DEFAULT 'pending' | Status |
| paid_at | TIMESTAMP NULL | Waktu cair |
| created_at, updated_at | TIMESTAMP | — |

---

### 3.8 Jasa Penulisan / Agency / Olah Data

#### `service_categories`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(191) | Nama kategori (Jasa Tulis, Olah Data, Cek Turnitin) |
| slug | VARCHAR(191) UQ | Slug unik |
| created_at, updated_at | TIMESTAMP | — |

#### `services` (katalog jasa milik writer/agency)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| writer_id | BIGINT UNSIGNED FK→users | Penjual (writer/agency) |
| category_id | BIGINT UNSIGNED FK→service_categories | Kategori |
| name | VARCHAR(191) | Nama layanan |
| description | TEXT NULL | Deskripsi |
| price | DECIMAL(15,2) | Harga |
| price_unit | ENUM('flat','per_page','per_package') | Satuan harga |
| is_active | TINYINT(1) DEFAULT 1 | Aktif/tidak |
| created_at, updated_at | TIMESTAMP | — |

#### `service_orders`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| uuid | UUID UQ | Identitas publik (URL order) |
| order_number | VARCHAR(40) UQ | Nomor order |
| user_id | BIGINT UNSIGNED FK→users | Klien |
| writer_id | BIGINT UNSIGNED FK→users | Penjual/penulis |
| service_id | BIGINT UNSIGNED FK→services | Layanan |
| project_id | BIGINT UNSIGNED NULL FK→projects | Project terkait (jika ada) |
| requirements | TEXT NULL | Kebutuhan/deskripsi klien |
| price | DECIMAL(15,2) | Total harga |
| status | ENUM('pending','accepted','in_progress','revision','completed','cancelled') | Status order |
| deadline_at | TIMESTAMP NULL | Batas waktu |
| created_at, updated_at | TIMESTAMP | — |

#### `order_messages` (chat klien ↔ penulis)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| order_id | BIGINT UNSIGNED FK→service_orders | Order |
| sender_id | BIGINT UNSIGNED FK→users | Pengirim |
| message | TEXT | Isi pesan |
| created_at | TIMESTAMP | — |

#### `order_revisions`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| order_id | BIGINT UNSIGNED FK→service_orders | Order |
| note | TEXT | Catatan revisi |
| status | ENUM('open','done') DEFAULT 'open' | Status revisi |
| created_at, updated_at | TIMESTAMP | — |

#### `order_deliverables` (hasil kerja)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| order_id | BIGINT UNSIGNED FK→service_orders | Order |
| file_path | VARCHAR(255) | Path file hasil |
| note | TEXT NULL | Catatan |
| created_at | TIMESTAMP | — |

---

### 3.9 Kampus / B2B SaaS (Tahap Lanjut)

#### `organizations`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(191) | Nama kampus/perusahaan |
| type | ENUM('university','company') | Tipe organisasi |
| slug | VARCHAR(191) UQ | Slug unik |
| billing_email | VARCHAR(191) NULL | Email tagihan |
| created_at, updated_at | TIMESTAMP | — |

#### `organization_members` (seat anggota)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| organization_id | BIGINT UNSIGNED FK→organizations | Organisasi |
| user_id | BIGINT UNSIGNED FK→users | Anggota |
| role | ENUM('owner','admin','member') DEFAULT 'member' | Peran di organisasi |
| status | ENUM('invited','active','inactive') DEFAULT 'invited' | Status |
| created_at, updated_at | TIMESTAMP | — |

#### `subscription_plans`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(191) | Nama paket |
| seats | INT UNSIGNED | Jumlah seat maksimal |
| price | DECIMAL(15,2) | Harga (IDR) |
| interval | ENUM('monthly','yearly') | Periode |
| features | JSON NULL | Fitur (array) |
| is_active | TINYINT(1) DEFAULT 1 | Aktif/tidak |
| created_at, updated_at | TIMESTAMP | — |

#### `subscriptions`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| organization_id | BIGINT UNSIGNED FK→organizations | Organisasi |
| plan_id | BIGINT UNSIGNED FK→subscription_plans | Paket |
| status | ENUM('trial','active','past_due','cancelled','expired') | Status |
| started_at | TIMESTAMP NULL | Mulai |
| ends_at | TIMESTAMP NULL | Berakhir |
| created_at, updated_at | TIMESTAMP | — |

---

### 3.10 Iklan & Promo

#### `ad_placements` (slot lokasi iklan)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| key | VARCHAR(64) UQ | Kode slot (sidebar, dashboard, builder) |
| name | VARCHAR(191) | Nama tampilan |
| is_active | TINYINT(1) DEFAULT 1 | Aktif/tidak |

#### `ad_campaigns`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| advertiser_id | BIGINT UNSIGNED FK→users | Pengiklan |
| placement_id | BIGINT UNSIGNED FK→ad_placements | Slot iklan |
| title | VARCHAR(191) | Judul iklan |
| target_url | VARCHAR(255) | URL tujuan |
| image | VARCHAR(255) NULL | Path gambar |
| budget | DECIMAL(15,2) | Anggaran |
| status | ENUM('draft','active','paused','ended') DEFAULT 'draft' | Status |
| start_at | TIMESTAMP NULL | Mulai tayang |
| end_at | TIMESTAMP NULL | Selesai tayang |
| created_at, updated_at | TIMESTAMP | — |

#### `ad_events` (tracking tayang/klik)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| campaign_id | BIGINT UNSIGNED FK→ad_campaigns | Campaign |
| type | ENUM('impression','click') | Jenis event |
| created_at | TIMESTAMP | — |

#### `coupons` (promo/voucher)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| code | VARCHAR(40) UQ | Kode kupon |
| type | ENUM('fixed','percent') | Tipe diskon |
| value | DECIMAL(10,2) | Nilai diskon |
| max_uses | INT UNSIGNED NULL | Maksimal pemakaian |
| used_count | INT UNSIGNED DEFAULT 0 | Terpakai |
| expires_at | TIMESTAMP NULL | Kedaluwarsa |
| is_active | TINYINT(1) DEFAULT 1 | Aktif/tidak |
| created_at, updated_at | TIMESTAMP | — |

#### `coupon_usages`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| coupon_id | BIGINT UNSIGNED FK→coupons | Kupon |
| user_id | BIGINT UNSIGNED FK→users | Pemakai |
| reference_type | VARCHAR(191) | Sumber (topup_order/subscription) |
| reference_id | BIGINT UNSIGNED | ID sumber |
| created_at | TIMESTAMP | — |

---

### 3.11 Support, Log & Notifikasi

#### `notifications`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED FK→users IDX | Penerima |
| type | VARCHAR(191) | Jenis notifikasi |
| data | JSON | Data notifikasi |
| read_at | TIMESTAMP NULL | Waktu dibaca |
| created_at, updated_at | TIMESTAMP | — |

#### `page_views` (traffic monitoring)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| uuid | UUID UQ | Identitas publik |
| user_id | BIGINT UNSIGNED NULL FK→users IDX | NULL = tamu/anonim |
| session_id | VARCHAR(64) IDX | ID sesi |
| path | VARCHAR(255) | Halaman yang dikunjungi |
| referrer | VARCHAR(255) NULL | Halaman asal |
| user_agent | VARCHAR(255) NULL | Browser/perangkat |
| ip | VARCHAR(45) NULL | Alamat IP |
| device | ENUM('desktop','mobile','tablet') NULL | Jenis perangkat |
| created_at | TIMESTAMP | — |

#### `audit_logs` (log aktivitas admin/sistem)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED NULL FK→users | Pelaku |
| action | VARCHAR(191) | Aksi (create/update/delete/approve) |
| model_type | VARCHAR(191) NULL | Model yang diubah |
| model_id | BIGINT UNSIGNED NULL | ID model |
| before | JSON NULL | Nilai sebelum |
| after | JSON NULL | Nilai sesudah |
| created_at | TIMESTAMP | — |

#### `tickets` (dukungan)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED FK→users | Pembuat tiket |
| subject | VARCHAR(191) | Judul |
| status | ENUM('open','in_progress','closed') DEFAULT 'open' | Status |
| priority | ENUM('low','normal','high') DEFAULT 'normal' | Prioritas |
| assigned_to | BIGINT UNSIGNED NULL FK→users | Admin yang menangani |
| created_at, updated_at | TIMESTAMP | — |

#### `ticket_messages`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | BIGINT UNSIGNED | PK |
| ticket_id | BIGINT UNSIGNED FK→tickets | Tiket |
| sender_id | BIGINT UNSIGNED FK→users | Pengirim |
| message | TEXT | Isi pesan |
| created_at | TIMESTAMP | — |

---

## 4. Relasi Antar Tabel (ERD Ringkas)

```
users ──< wallets ──< credit_transactions
users ──< projects ──< project_blocks
users ──< media / fonts
users ──< payments ──< topup_orders
users ──< credit_submissions
users ──< referral_codes ──< referrals ──< affiliate_commissions
users ──< affiliate_payouts
users ──< service_orders ──< order_messages / order_revisions / order_deliverables
users ──< services ──< service_categories
organizations ──< organization_members >── users
organizations ──< subscriptions ──> subscription_plans
users ──< ad_campaigns ──< ad_events
users ──< notifications / tickets ──< ticket_messages

roles <── role_has_permissions ──> permissions
users <── model_has_roles ──> roles
```

Keterangan simbol: `──<` = one-to-many, `>──<` = many-to-many.

---

## 5. Alur Penting (Flow)

### 5.1 Topup Kredit
1. User pilih `credit_packages` → buat `topup_orders` (status `pending`).
2. Buat `payments` (status `pending`) → kirim ke gateway (Midtrans/Xendit/Stripe).
3. Callback gateway → `payments.status = paid`, `topup_orders.status = paid`.
4. Tambah `credit_transactions` (type `credit`, reason `topup`) & update `wallets.balance`.

### 5.2 Program Dapatkan Kredit (verifikasi admin)
1. User kirim URL → `credit_submissions` (status `pending`).
2. `moderator`/`admin` review → `approved` atau `rejected`.
3. Jika `approved`: set `credits_awarded`, buat `credit_transactions` (reason `submission_reward`), update `wallets.balance`.
4. `reviewed_by` & `reviewed_at` dicatat, masuk ke `audit_logs`.

### 5.3 Affiliate / Referral
1. User A dapat `referral_codes`.
2. User B daftar pakai kode A → `referrals` (status `registered`).
3. Saat B topup (memenuhi syarat) → `referrals.status = qualified`, buat `affiliate_commissions`.
4. Admin approve komisi → `affiliate_payouts` untuk pencairan.

### 5.4 Order Jasa Penulisan / Olah Data
1. Klien buat `service_orders` (status `pending`).
2. Penulis `accepted` → `in_progress`.
3. Diskusi lewat `order_messages`; revisi lewat `order_revisions`.
4. Hasil dikirim lewat `order_deliverables` → status `completed` → rating ke `writer_profiles`.

### 5.5 Kerjasama Kampus / B2B
1. `partner` buat `organizations`.
2. Undang anggota → `organization_members` (status `invited` → `active`).
3. Pilih `subscription_plans` → buat `subscriptions`.
4. Jumlah `organization_members` aktif dibatasi oleh `subscription_plans.seats`.

### 5.6 Iklan
1. `advertiser` buat `ad_campaigns` (pilih `ad_placements`).
2. Admin approve → status `active`.
3. Tracking `ad_events` (impression/click) untuk laporan.

### 5.7 Monitoring Super-admin

1. `page_views` mencatat kunjungan → agregat per hari/path untuk grafik traffic.
2. `payments` + `credit_transactions` → total pendapatan, saldo kredit, dan riwayat transaksi.
3. `service_orders` + `credit_submissions` + `tickets` → antrian yang perlu ditinjau.
4. `audit_logs` → jejak aksi admin & sistem (untuk audit).
5. Super-admin melihat seluruh data lintas-user; admin lain dibatasi role/permission-nya.

---

## 6. Catatan Teknis & Rekomendasi

1. **RBAC** — gunakan `spatie/laravel-permission` (sudah menyediakan tabel roles/permissions/pivot).
2. **Payment Gateway** — untuk IDR: **Midtrans** / **Xendit** / **DOKU**; untuk B2B internasional: **Stripe** (`laravel/cashier`).
3. **Idempotency** — `payments.invoice_number` dan `provider_ref` harus unik agar callback ganda tidak menggandakan kredit.
4. **Ledger kredit** — `credit_transactions` bersifat append-only (tidak pernah di-update/hapus) agar bisa diaudit.
5. **Soft delete** — terapkan `deleted_at` pada `projects`, `services`, `coupons`, dan tabel referensial lain.
6. **Indexing** — beri index pada kolom yang sering jadi filter: `projects.category`, `projects.is_public`, `credit_transactions.user_id`, `ad_events.campaign_id`.
7. **Skalabilitas** — `project_blocks.content`/`snapshot` bisa dipindah ke `JSONB` (PostgreSQL) atau storage object bila dokumen besar.
8. **Migrasi dari localStorage** — data sementara saat ini dipetakan sebagai berikut:
   - `tulisin:project:{id}` → `projects` + `project_blocks`
   - `tulisin.imageLibrary` → `media`
   - `tulisin.customFonts` → `fonts`
   - `tulisin.creditSubmissions` → `credit_submissions`

---

## 7. Saran Tahapan Implementasi

| Tahap | Cakupan | Tabel Utama |
|-------|---------|-------------|
| 1 | MVP: auth, project, kredit, topup | users, profiles, projects, blocks, wallets, credit_transactions, payments, topup_orders |
| 2 | Program kredit + moderasi | credit_submissions, audit_logs |
| 3 | Affiliate | referral_codes, referrals, affiliate_commissions, affiliate_payouts |
| 4 | Jasa penulisan/agency | services, service_orders, order_messages, revisions, deliverables |
| 5 | B2B & iklan (lanjut) | organizations, subscriptions, ad_campaigns, coupons |

> Dokumen ini bisa dijadikan acuan untuk membuat migration Laravel (`php artisan make:migration`).
