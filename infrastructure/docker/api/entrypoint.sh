#!/bin/sh
# Prepares Laravel caches at container start. Migrations are run explicitly
# by the deploy pipeline (see docs/deployment/README.md), never implicitly
# by every replica.
set -e

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is not set" >&2
  exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
