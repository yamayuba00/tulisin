# Setup VPS — Queue Worker & Supervisor

Panduan untuk menjalankan worker queue AI (`GenerateAiJob`) di server produksi (Linux),
agar permintaan `/api/ai/generate` diproses di belakang layar dan bisa jalan bersamaan.

## 1. Konfigurasi `.env`

Pastikan nilai berikut ada di file `.env`:

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=150
```

- `QUEUE_CONNECTION=database` → job disimpan di tabel `jobs`.
- `DB_QUEUE_RETRY_AFTER=150` → harus lebih besar dari `$timeout` job (120 detik)
  agar job tidak dianggap "stuck" dan diproses ganda saat worker crash.

## 2. Install Supervisor

```bash
sudo apt update
sudo apt install -y supervisor
```

## 3. Buat konfigurasi worker

Buat file `/etc/supervisor/conf.d/tulissin-ai.conf`:

```ini
[program:tulissin-ai-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/tulisin/artisan queue:work --queue=ai --sleep=3 --tries=1 --max-time=3600
directory=/var/www/tulisin
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=5
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/tulisin/storage/logs/worker.log
```

Penjelasan penting:

| Kunci | Keterangan |
|-------|-----------|
| `numprocs=5` | 5 worker berjalan paralel (5 job AI bersamaan) |
| `process_name=%(program_name)s_%(process_num)02d` | Wajib agar `numprocs` bisa membuat banyak proses |
| `--queue=ai` | Cocok dengan `$queue = 'ai'` pada `GenerateAiJob` |
| `--max-time=3600` | Worker restart tiap 1 jam (hindari kebocoran memori) |
| `stopasgroup` + `killasgroup` | Worker berhenti bersih saat restart/deploy |

> Sesuaikan `command` dan `directory` dengan lokasi proyek di server.
> Jika ada job lain (PDF export `exports`, email `emails`), ubah menjadi
> `--queue=ai,exports,emails` atau buat `[program:]` terpisah.

## 4. Aktifkan

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tulissin-ai-worker:*
```

## 5. Perintah umum

```bash
# Cek status worker
sudo supervisorctl status

# Restart semua worker AI
sudo supervisorctl restart tulissin-ai-worker:*

# Stop semua worker AI
sudo supervisorctl stop tulissin-ai-worker:*

# Lihat log
tail -f /var/www/tulisin/storage/logs/worker.log
```

## 6. Setelah deploy kode

```bash
php artisan queue:restart
```

Ini akan meminta semua worker berhenti dengan bersih setelah job berjalan selesai,
lalu Supervisor otomatis menjalankannya kembali (karena `autorestart=true`).

## Catatan

- Worker lokal (Windows/Laragon) tidak memakai Supervisor. Jalankan manual:
  `php artisan queue:work --queue=ai` (buka beberapa terminal untuk worker paralel),
  atau pasang sebagai Windows service lewat NSSM.
- Jumlah worker (`numprocs`) bisa dinaikkan sesuai RAM server. Sebagai acuan awal,
  5 worker cukup untuk beban sedang.
