# Deployment & Local Setup

## 1. Environments

| Env | Purpose | Data | MLS |
|---|---|---|---|
| development | Local work | Seeded demo accounts (`DevelopmentSeeder`, refuses non-local envs) | Fixture provider only |
| testing | CI / automated tests | Ephemeral DB per run | Faked HTTP only (`Http::preventStrayRequests`) |
| staging | Pre-release validation | Anonymized/synthetic | Sandbox connections only |
| production | Live | Real | Only authorized providers (`MLS_SYNC_ENABLED=true`) |

## 2. Local development without Docker

Requirements: PHP 8.3 (pdo_pgsql, redis, intl, zip, bcmath), Composer 2, Node 22, PostgreSQL 16 +
PostGIS 3, Redis 7, Flutter 3.47.

```bash
# Database (once)
createuser -P hitlease            # choose a local password
createdb -O hitlease hitlease
createdb -O hitlease hitlease_test
psql -d hitlease -c 'CREATE EXTENSION IF NOT EXISTS postgis'
psql -d hitlease_test -c 'CREATE EXTENSION IF NOT EXISTS postgis'

# API — http://localhost:8000
cd apps/api
cp .env.example .env              # set DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate --seed        # prints the demo accounts' password (<role>@hitlease.test)
php artisan serve
php artisan storage:link          # serves listing photos from storage/app/public
php artisan queue:work redis --queue=default,notifications,mls-sync,mls-media
php artisan schedule:work         # licence expiry + MLS sync scheduling

# Web — http://localhost:3000
cd apps/web && cp .env.example .env.local && npm ci && npm run dev

# Mobile (Android emulator reaches the host as 10.0.2.2)
cd apps/mobile && flutter pub get && flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Tests: `php artisan test` (needs `hitlease_test` DB), `npm test`, `flutter test`.
E2E: `php artisan db:seed --class=E2ESeeder` (fictitious data; refuses non-local envs), API on :8000,
then `npm run build && npm run test:e2e`. The broker tests mutate the seed — re-seed before re-running
(the seeder also removes photos uploaded by earlier runs). Repeated runs within an hour can trip the
per-IP auth limiter; locally raise `SECURITY_AUTH_REQUESTS_PER_MINUTE` or run `php artisan cache:clear`.

## 3. Docker Compose

```bash
cp .env.example .env   # set DB_PASSWORD; APP_KEY via the command below
docker compose run --rm api php artisan key:generate --show   # paste into APP_KEY
docker compose up -d --build
docker compose run --rm api php artisan migrate --force
docker compose run --rm api php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

Services: `postgres` (postgis/postgis:16-3.4), `redis`, `api` (PHP-FPM), `queue`, `scheduler`,
`web` (Next.js standalone), `nginx` (reverse proxy: `api.localhost` → API, everything else → web).
The web container renders SSR pages against `API_INTERNAL_URL=http://api-internal` (an nginx alias on
the Compose network); browsers keep using the public `NEXT_PUBLIC_API_URL`. Listing photos live on
the `api-media` volume (`MEDIA_DISK=public`) and nginx serves them read-only under
`http://api.localhost/storage/` (images only).

## 4. Production checklist

* `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY` (kept in the secret store; rotating it
  invalidates encrypted MFA secrets and MLS credentials — plan a re-encryption migration).
* Same registrable domain for web and API (e.g. `www.example.com` + `api.example.com`):
  `SESSION_DOMAIN=.example.com`, `SESSION_SECURE_COOKIE=true`,
  `SANCTUM_STATEFUL_DOMAINS=www.example.com`, `CORS_ALLOWED_ORIGINS=https://www.example.com`,
  `FRONTEND_URL=https://www.example.com`, `TRUSTED_PROXIES` set to the load balancer/Cloudflare ranges.
* Database: managed PostgreSQL 16 with PostGIS, TLS (`DB_SSLMODE=require`), least-privilege user,
  PITR backups + weekly restore test.
* Redis with auth/TLS; queue workers supervised (Compose `restart`, systemd or the platform);
  Horizon or queue metrics for monitoring.
* Migrations run **once per release** from the pipeline (`php artisan migrate --force`) before
  rolling out new containers; then `db:seed --class=RolesAndPermissionsSeeder --force` (idempotent).
* Scheduler must run (`php artisan schedule:work` / cron `schedule:run` every minute): it expires
  agent licences daily at 00:15 America/New_York (`hitlease:licenses:expire`), queues due MLS syncs
  every 5 minutes (`hitlease:mls:sync-due`) and a nightly reconcile at 03:30 America/New_York.
  These MLS commands do nothing until `MLS_SYNC_ENABLED=true` and a provider is authorized.
  Saved-search alerts run daily at 07:45 and weekly on Mondays at 07:50 America/New_York
  (`hitlease:saved-searches:alert --frequency=daily|weekly`).
* Set the same random `API_SSR_KEY` (`openssl rand -hex 32`) on the API and the web server so
  server-rendered pages are rate-limited per visitor instead of per web server. Without it, all SSR
  traffic shares the web server's limit.
* Queue workers must consume `default,notifications,mls-sync,mls-media,documents`.
* Media: `MEDIA_DISK=public` needs `php artisan storage:link` and persistent, shared storage for
  every API/queue replica. **Cloudflare R2 is the intended production disk and is blocked** until
  the bucket, credentials and the `league/flysystem-aws-s3-v3` driver are provided.
* Observability: set `SENTRY_LARAVEL_DSN` / OTLP endpoint (integration scheduled for Sprint 12),
  alerts on 5xx rate, queue backlog, failed jobs, MLS sync staleness.
* Mail: verified sender domain (SPF, DKIM, DMARC).
* Mobile: release builds with `--dart-define=API_BASE_URL=https://api.example.com` (HTTPS only).

## 5. CI/CD

* `.github/workflows/ci.yml`: API (Pint, composer audit, migrations up/down/up, PHPUnit on
  PostGIS), Web (ESLint, tsc, Vitest, build, npm audit), E2E (Playwright against the real API),
  Mobile (codegen drift, format, analyze, tests, debug APK), OpenAPI lint, Docker builds.
* `.github/workflows/deploy.yml`: manual, environment-protected; builds and pushes versioned images
  to GHCR. The rollout step is a documented placeholder until the hosting platform is chosen.
