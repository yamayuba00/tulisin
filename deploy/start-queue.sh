#!/usr/bin/env bash
# ============================================================================
# Fallback sederhana (tanpa Supervisor/systemd) untuk menjalankan queue worker.
# Jalankan di project root VPS:  bash deploy/start-queue.sh
#
# PENTING: sesuaikan PROJECT_PATH & PHP_BIN bila berbeda.
# ============================================================================
set -e

PROJECT_PATH="/var/www/tulisin"
PHP_BIN="php"

cd "$PROJECT_PATH"

# Worker antrian default (notifikasi/email — cepat)
nohup "$PHP_BIN" artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600 \
    >> storage/logs/worker-default.log 2>&1 &

# Worker antrian exports (render PDF — lama)
nohup "$PHP_BIN" artisan queue:work --queue=exports --sleep=3 --tries=1 --timeout=600 --max-time=3600 \
    >> storage/logs/worker-exports.log 2>&1 &

echo "Queue worker default & exports berjalan di background."
echo "Cek log: storage/logs/worker-default.log dan storage/logs/worker-exports.log"
