#!/usr/bin/env bash
# Restore a Hitlease database dump into a NEW database and verify it (restore drill).
#
#   PGHOST=... PGUSER=... PGPASSWORD=... [PG_ADMIN_USER=... PG_ADMIN_PASSWORD=...] \
#     infrastructure/scripts/restore.sh backups/hitlease-db-<stamp>.dump hitlease_restore
#
# PostGIS can only be created by a superuser (rds_superuser / cloudsqlsuperuser on managed
# services): set PG_ADMIN_USER so the extensions are created first; the app role restores the rest.
#
# Refuses to overwrite an existing database. To promote a verified copy, point the application at
# it (DB_DATABASE) during a maintenance window — see docs/operations/runbooks.md#restore.
set -euo pipefail

dump="${1:?Usage: restore.sh <dump-file> <new-database-name>}"
target="${2:?Usage: restore.sh <dump-file> <new-database-name>}"
[[ "$target" =~ ^[a-z_][a-z0-9_]{0,62}$ ]] || { echo "Invalid database name: $target" >&2; exit 2; }

sums="$(dirname "$dump")/$(basename "$dump" | sed -E 's/hitlease-db-(.*)\.dump/hitlease-\1.sha256/')"
if [[ -f "$sums" ]]; then
  (cd "$(dirname "$dump")" && grep " $(basename "$dump")\$" "$(basename "$sums")" | sha256sum --check --quiet) && echo "✓ checksum"
else
  echo "! no checksum file found next to the dump" >&2
fi

if psql -d postgres -Atc "select 1 from pg_database where datname = '$target'" | grep -q 1; then
  echo "Database $target already exists; choose a new name." >&2
  exit 3
fi

started=$(date +%s)
createdb "$target"
admin_psql() { PGUSER="${PG_ADMIN_USER:-${PGUSER:-}}" PGPASSWORD="${PG_ADMIN_PASSWORD:-${PGPASSWORD:-}}" psql -v ON_ERROR_STOP=1 -q "$@"; }
# The dump's own CREATE EXTENSION IF NOT EXISTS statements then become no-ops.
for ext in postgis btree_gist; do
  admin_psql -d "$target" -c "CREATE EXTENSION IF NOT EXISTS $ext"
done
# Extensions (and their comments) were created above by the admin role; skip them in the dump.
list="$(mktemp)"
trap 'rm -f "$list"' EXIT
pg_restore --list "$dump" | grep -vE '(EXTENSION - |COMMENT - EXTENSION |TABLE DATA public spatial_ref_sys )' > "$list"
pg_restore --no-owner --no-privileges --exit-on-error --use-list="$list" --dbname="$target" "$dump"
elapsed=$(( $(date +%s) - started ))

echo "✓ restored into $target in ${elapsed}s"
psql -d "$target" -At -F $'\t' <<'SQL'
select 'migrations', count(*) from migrations
union all select 'users', count(*) from users
union all select 'properties', count(*) from properties
union all select 'leads', count(*) from leads
union all select 'appointments', count(*) from appointments
union all select 'transactions', count(*) from transactions
union all select 'documents', count(*) from documents
union all select 'audit_logs', count(*) from audit_logs
union all select 'postgis', (select count(*) from pg_extension where extname = 'postgis');
SQL
