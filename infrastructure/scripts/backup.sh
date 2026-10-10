#!/usr/bin/env bash
# App Inmobiliaria backup: PostgreSQL (custom format) + private documents, with SHA-256 checksums.
#
#   PGHOST=... PGUSER=... PGPASSWORD=... PGDATABASE=app_inmobiliaria BACKUP_DIR=/backups \
#   DOCUMENTS_PATH=/var/www/html/storage/app/private infrastructure/scripts/backup.sh
#
# Managed PostgreSQL PITR remains the primary mechanism; this produces portable, verifiable copies
# for restore drills and off-site retention. Store the output on encrypted storage only — it
# contains personal data. Old backups are pruned after BACKUP_RETENTION_DAYS (default 30).
set -euo pipefail

: "${PGDATABASE:?Set PGDATABASE (and PGHOST/PGUSER/PGPASSWORD as needed)}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"
umask 077

db_file="$BACKUP_DIR/app-inmobiliaria-db-$STAMP.dump"
echo "→ Dumping database $PGDATABASE"
pg_dump --format=custom --no-owner --no-privileges --compress=6 --exclude-table-data=public.spatial_ref_sys --file="$db_file" "$PGDATABASE"
# spatial_ref_sys ships with PostGIS. A dump that pg_restore cannot list is not a backup.
pg_restore --list "$db_file" >/dev/null

files=("$db_file")
if [[ -n "${DOCUMENTS_PATH:-}" && -d "${DOCUMENTS_PATH}" ]]; then
  docs_file="$BACKUP_DIR/app-inmobiliaria-documents-$STAMP.tar.gz"
  echo "→ Archiving documents from $DOCUMENTS_PATH"
  tar -C "$DOCUMENTS_PATH" -czf "$docs_file" .
  files+=("$docs_file")
fi

(cd "$BACKUP_DIR" && sha256sum "${files[@]##*/}" > "app-inmobiliaria-$STAMP.sha256")
echo "→ Wrote ${files[*]} (+ checksums)"

find "$BACKUP_DIR" -maxdepth 1 -name 'app-inmobiliaria-*' -type f -mtime "+$RETENTION_DAYS" -print -delete || true
