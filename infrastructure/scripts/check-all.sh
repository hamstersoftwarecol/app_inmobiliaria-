#!/usr/bin/env bash
# Runs the same fast checks as CI for all three apps (requires local toolchains
# and a PostgreSQL+PostGIS test database for the API — see docs/deployment).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

echo "▶ API"
(cd "$ROOT/apps/api" && vendor/bin/pint --test && php artisan test)

echo "▶ Web"
(cd "$ROOT/apps/web" && npm run lint && npm run typecheck && npm test)

if command -v flutter >/dev/null 2>&1; then
  echo "▶ Mobile"
  (cd "$ROOT/apps/mobile" && dart format --set-exit-if-changed lib test && flutter analyze && flutter test)
else
  echo "⚠ flutter not installed — skipping mobile checks"
fi
