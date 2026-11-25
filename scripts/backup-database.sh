#!/usr/bin/env bash
# Backup database Satu POS untuk VPS Linux (dipanggil cron).
#
# Contoh crontab (backup tiap hari 02:30):
#   30 2 * * * /var/www/satu-pos/scripts/backup-database.sh
#
# Log: storage/logs/backup-database.log
# Exit code 0 = sukses, bukan 0 = gagal (cron akan mengirim email bila dikonfigurasi).

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_FILE="${APP_DIR}/storage/logs/backup-database.log"

cd "$APP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

echo "=== $(date '+%Y-%m-%d %H:%M:%S') mulai backup database ===" >> "$LOG_FILE"

if php artisan backup:database --keep="${DB_BACKUP_KEEP:-14}" >> "$LOG_FILE" 2>&1; then
    echo "=== $(date '+%Y-%m-%d %H:%M:%S') backup selesai ===" >> "$LOG_FILE"
    exit 0
else
    STATUS=$?
    echo "=== $(date '+%Y-%m-%d %H:%M:%S') backup GAGAL (exit ${STATUS}) ===" >> "$LOG_FILE"
    exit "$STATUS"
fi
