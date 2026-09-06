# Panduan Deploy ke VPS

Panduan ini untuk deploy aplikasi **Tulisin** (Laravel + Vue 3 / Vite SPA) ke VPS Linux
(Ubuntu/Debian). Asumsi pakai **Nginx + PHP-FPM + PostgreSQL**.

---

## 1. Ringkasan cron & queue di aplikasi ini

### Cron / Scheduler — ADA
Ada 1 scheduled command yang berjalan via Laravel Scheduler:

| Command | Frekuensi | Fungsi |
|---|---|---|
| `subscriptions:remind-expiring` | Harian | Kirim email pengingat 5 hari sebelum masa langganan habis |

Scheduler didefinisikan di `routes/console.php`:

```php
Schedule::command('subscriptions:remind-expiring')->daily();
```

> Wajib memasang cron entry `php artisan schedule:run` setiap menit (lihat bagian 9).

### Queue — ADA (driver: database)
Aplikasi memakai queue driver **`database`** (`QUEUE_CONNECTION=database` di `.env`).
Tabel `jobs` / `failed_jobs` sudah ada di migration.

| Kebutuhan | Status |
|---|---|
| Tabel `jobs` & `failed_jobs` | Sudah di-migrate |
| Queue worker | Perlu dijalankan (Supervisor) |
| Email blast & notifikasi | Saat ini masih **sinkron** (belum `ShouldQueue`), jadi queue worker opsional untuk sekarang |

> Catatan: untuk volume email besar (email blast promo ke banyak user), disarankan
> mengubah notification menjadi `ShouldQueue` agar terkirim lewat queue. Untuk sekarang,
> tetap sediakan worker-nya supaya siap dipakai.

---

## 2. Prasyarat server

- OS: Ubuntu 22.04 / 24.04 atau Debian 12
- **PHP 8.3+** (aplikasi butuh PHP `^8.3`)
- **Composer 2**
- **PostgreSQL 14+**
- **Nginx**
- **Node.js 20+** (hanya untuk build frontend)
- **Supervisor** (untuk queue worker)
- `git`

Ekstensi PHP yang wajib:

```
pdo_pgsql mbstring openssl bcmath ctype curl dom fileinfo
json tokenizer xml zip gd intl
```

---

## 3. Instalasi paket dasar

```bash
sudo apt update && sudo apt upgrade -y

# Nginx, git, curl, unzip (PostgreSQL pakai cloud, tidak perlu install lokal)
sudo apt install -y nginx git curl unzip

# Supervisor
sudo apt install -y supervisor

# PHP 8.3 (contoh Ubuntu 24.04 pakai repo ondrej/php)
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring \
  php8.3-bcmath php8.3-curl php8.3-dom php8.3-fileinfo php8.3-gd \
  php8.3-intl php8.3-xml php8.3-zip php8.3-opcache

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS (via NodeSource) — sudah cukup untuk Vite 8 (butuh Node 20.19+)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Catatan npm:
# Notif "npm 12 tersedia" bisa diabaikan (bukan error). npm 12 butuh Node 22+.
# Jika tetap ingin npm terbaru, upgrade Node dulu ke 22 LTS:
#   curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
#   sudo apt install -y nodejs
#   sudo npm install -g npm@12

# Chromium (untuk fitur export PDF via headless browser)
sudo apt install -y chromium
# Jika binary terdeteksi di path berbeda, set di .env:
# CHROME_BIN=/usr/bin/chromium
```

---

## 4. Siapkan database PostgreSQL

```bash
sudo -u postgres psql
```

Di dalam `psql`:

```sql
CREATE USER tulisin WITH PASSWORD 'GANTI_PASSWORD_AMAN';
CREATE DATABASE tulisin OWNER tulisin;
GRANT ALL PRIVILEGES ON DATABASE tulisin TO tulisin;
\q
```

---

## 5. Deploy aplikasi

```bash
cd /var/www
sudo git clone GIT_REPO_URL tulisin
cd tulisin

# Pastikan user web bisa akses
sudo chown -R $USER:www-data .
```

Install dependency:

```bash
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts
npm run build
```

---

## 6. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` dengan `nano .env`, isi minimal:

```env
APP_NAME="Tulisin"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com
APP_TIMEZONE=Asia/Jakarta

# Wajib: domain SPA yang boleh memakai auth cookie Sanctum.
# Isi sesuai domain yang kamu akses (dan varian www bila dipakai).
SANCTUM_STATEFUL_DOMAINS=domain-anda.com,www.domain-anda.com

# Hanya "true" bila situs diakses via HTTPS (sudah SSL). Kalau masih HTTP/IP
# (tanpa SSL), wajib "false" — kalau tidak cookie sesi ber-flag Secure tidak
# dikirim browser lewat HTTP, dan semua POST akan kena "CSRF token mismatch".
SESSION_SECURE_COOKIE=false

DB_CONNECTION=pgsql
DB_HOST=host-cloud-provider-anda.com
DB_PORT=6432
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database
DB_SSLMODE=require

# Queue (database driver)
QUEUE_CONNECTION=database

# Mail (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.provider-anda.com
MAIL_PORT=587
MAIL_USERNAME=user@domain-anda.com
MAIL_PASSWORD=password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@domain-anda.com"
MAIL_FROM_NAME="${APP_NAME}"

# ---- Pembayaran SumoPod ----
PAYMENT_PROVIDER=sumopod
PAYMENT_FEE_FIXED=2000
PAYMENT_FEE_PERCENT=0

# Saat produksi: pakai live
SUMOPOD_SANDBOX=false
SUMOPOD_LIVE_API_KEY=API_KEY_LIVE_SUMOPOD

# Verifikasi webhook — isi SALAH SATU:
SUMOPOD_WEBHOOK_SECRET=SECRET_WEBHOOK      # signature Svix (prefix whsec_)
# SUMOPOD_WEBHOOK_TOKEN=                   # token statis (header X-Webhook-Token)

# URL redirect (WAJIB https domain publik)
PAYMENT_SUCCESS_RETURN_URL=https://domain-anda.com/apps/u/topup?status=success
PAYMENT_CANCEL_RETURN_URL=https://domain-anda.com/apps/u/topup?status=cancel
```

> `APP_URL` dan `PAYMENT_*_RETURN_URL` harus memakai domain publik https,
> karena SumoPod menolak `localhost`.

> **Error "Session store not set on request"** biasanya karena `SANCTUM_STATEFUL_DOMAINS`
> belum memuat domain produksi. Auth aplikasi ini memakai cookie/session Sanctum (SPA),
> jadi domain frontend harus terdaftar di sana. Pastikan nilainya sama persis dengan
> domain yang dipakai akses (termasuk `www.` bila ada), lalu `config:clear` + `config:cache`.

### PostgreSQL cloud & PgBouncer (port 6432)

Provider PostgreSQL cloud biasanya memberi dua endpoint: koneksi **langsung** (port
`5432`) dan koneksi **pooled/PgBouncer** (port `6432`, mode transaction pooling).
Kalau `.env` kamu memakai `DB_PORT=6432`, maka koneksi lewat PgBouncer.

Masalah yang muncul: PgBouncer transaction pooling bisa melepas *prepared statement*
server-side di antara transaksi, sehingga muncul error:

```
SQLSTATE[26000]: prepared statement "pdo_stmt_00000001" does not exist
```

Solusinya sudah diterapkan di `config/database.php`: koneksi `pgsql` mengaktifkan
emulasi prepared statement (`PDO::ATTR_EMULATE_PREPARES => true`), jadi query tetap
jalan normal lewat PgBouncer. Tidak perlu ubah apa-apa di `.env` untuk ini.

Setelah deploy/ubah `config/database.php`, jalankan ulang cache config:

```bash
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache
```

> Opsional: kalau provider menyediakan endpoint langsung (port 5432) dan kamu mau
> migrasi/DDL lebih aman, kamu bisa memakai fitur `pooled`/`direct` bawaan Laravel 13
> (lihat dokumentasi "Pooled PostgreSQL Connections"). Untuk sekarang, emulasi di atas
> sudah cukup untuk query aplikasi.

---

## 7. Migrasi & seed

```bash
php artisan migrate --force
php artisan db:seed --force
```

Cache & permission:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Nginx

Buat file `/etc/nginx/sites-available/tulisin`:

```nginx
server {
    listen 80;
    server_name domain-anda.com;

    root /var/www/tulisin/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan:

```bash
sudo ln -s /etc/nginx/sites-available/tulisin /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 9. Cron (Scheduler)

Tambahkan cron agar `schedule:run` jalan tiap menit:

```bash
sudo crontab -e
```

Isi:

```cron
* * * * * cd /var/www/tulisin && sudo -u www-data php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi daftar schedule:

```bash
php artisan schedule:list
```

---

## 10. Queue worker (Supervisor)

Buat file `/etc/supervisor/conf.d/tulisin-worker.conf`:

```ini
[program:tulisin-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/tulisin/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/tulisin/storage/logs/worker.log
stopwaitsecs=3600
```

Muat ulang Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status tulisin-worker:*
```

Cek antrian gagal:

```bash
php artisan queue:failed
```

---

## 11. HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d domain-anda.com
```

---

## 12. Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 13. Konfigurasi webhook SumoPod (live)

Setelah live, daftarkan webhook di dashboard SumoPod ke:

```
https://domain-anda.com/api/payments/webhook/sumopod
```

Webhook diverifikasi dulu sebelum diproses (menolak request palsu). Pilih salah satu:

1. **Signature Svix-style** — isi `SUMOPOD_WEBHOOK_SECRET` (prefix `whsec_`) sesuai
   secret dari dashboard. Provider memverifikasi header `svix-id`, `svix-timestamp`,
   dan `svix-signature` (HMAC-SHA256).
2. **Token statis** — isi `SUMOPOD_WEBHOOK_TOKEN` (prefix `whtok_`). Provider
   memverifikasi header `X-Webhook-Token`.

> Bila keduanya diisi, token diprioritaskan. Webhook dengan signature/token tidak
> valid dibalas `401` dan tidak diproses.

> Pemetaan field respons/webhook SumoPod saat ini diasumsikan (`status`, `order_id`,
> `payment_id`). **Samakan nama field aktual** di
> `app/Services/Payments/SumoPodProvider.php` sebelum go-live.

---

## 14. Email notifikasi

Pastikan `MAIL_*` di `.env` sudah benar. Email yang dikirim:
- Verifikasi email (registrasi)
- Reset password
- Reminder langganan (5 hari sebelum habis)
- Notifikasi pembelian ke admin
- Email blast promo ke user

---

## 15. Update / deploy ulang

```bash
cd /var/www/tulisin
git pull
composer install --no-dev --optimize-autoloader
composer dump-autoload
npm ci --ignore-scripts
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart tulisin-worker:*
```

---

## 16. Cek kesehatan

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
curl -I https://domain-anda.com
```
