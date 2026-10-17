#!/usr/bin/env bash
#
# Sprint 19 hardening artifact — cPanel cron-triggered backup.
# See docs/backup-restore-runbook.md for the full policy and restore steps.
#
# Usage (cPanel Cron Job):
#   /bin/bash /path/to/backend/scripts/backup.sh
#
# Expects the following environment variables to be available to the cron
# job (cPanel cron jobs do not source .env automatically — set these in the
# cron command itself or source a separate secrets file kept outside the
# public web root, e.g.:
#   BACKUP_DIR=/home/user/backups DB_HOST=... DB_PORT=... DB_DATABASE=... \
#   DB_USERNAME=... DB_PASSWORD=... /bin/bash /path/to/backend/scripts/backup.sh
#
set -euo pipefail

: "${BACKUP_DIR:?BACKUP_DIR must be set to a directory OUTSIDE the public web root}"
: "${DB_HOST:?}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?}"
: "${DB_USERNAME:?}"
: "${DB_PASSWORD:?}"

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"

echo "[backup] Dumping database ($DB_DATABASE) ..."
mysqldump --single-transaction --quick --routines --triggers \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > "$BACKUP_DIR/db-${TIMESTAMP}.sql.gz"

echo "[backup] Archiving media (storage/app/public) ..."
tar -czf "$BACKUP_DIR/media-${TIMESTAMP}.tar.gz" -C "$APP_ROOT" storage/app/public

echo "[backup] Done. Files written to $BACKUP_DIR:"
echo "  db-${TIMESTAMP}.sql.gz"
echo "  media-${TIMESTAMP}.tar.gz"
echo "[backup] Reminder: back up the current .env separately, from a secure secrets location."

# Retention is intentionally NOT automated here: pruning old backups is an
# operational decision (see docs/backup-restore-runbook.md for the proposed
# retention policy). Wire a separate, explicitly reviewed cleanup step once
# the retention policy is confirmed with the project owner.
