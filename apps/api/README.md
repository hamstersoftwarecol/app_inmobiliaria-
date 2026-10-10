# Hitlease Real Estate — API (Laravel)

Laravel 13 · PHP 8.3 · Sanctum · PostgreSQL 16 + PostGIS · Redis. Versioned REST API under
`/api/v1`; contract in [`docs/api/openapi.yaml`](../../docs/api/openapi.yaml).

```bash
cp .env.example .env && composer install && php artisan key:generate
php artisan migrate --seed      # local: demo accounts <role>@hitlease.test (password printed)
php artisan serve               # http://localhost:8000/api/v1/health
php artisan test                # needs the hitlease_test PostgreSQL database with PostGIS
vendor/bin/pint                 # code style
```

Layout: `app/Domain` · `app/Application` · `app/Infrastructure` · `app/Http` · `app/Policies` ·
`database/migrations` · `tests/{Feature,Unit,Fixtures}`. See `docs/architecture/overview.md`.
