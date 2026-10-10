# Operations runbooks

Practical procedures for running App Inmobiliaria Real Estate. Commands assume the Docker Compose stack
(`docker compose exec api …`); on other platforms run the same `php artisan` commands in an API
container. Times are America/New_York unless stated.

## 1. Health and monitoring

| Probe | Use | Healthy |
|---|---|---|
| `GET /api/v1/health` | Liveness (container restart) | `200 {"status":"ok"}` |
| `GET /api/v1/health/ready` | Readiness (load balancer, deploy gate) | `200`, `status` `ok` (or `degraded`) |

`/health/ready` returns **503** when the database (with PostGIS), cache or document storage fails.
It reports **degraded** (still 200) when the queue backlog exceeds `HEALTH_MAX_QUEUE_BACKLOG`
(default 1000) or the scheduler heartbeat is older than 3 minutes. Neither probe is rate-limited
or reveals hosts, versions or error text.

Alert on: readiness non-200 for 2 minutes; `degraded` for 15 minutes; 5xx rate > 1 % for 5 minutes;
`failed_jobs` growth; MLS connection `is_stale` in `/admin/mls`. Logs: set `LOG_CHANNEL=json`
in containers — one JSON object per line on stderr, each carrying the `request_id` that is also
returned as `X-Request-Id` and stored on audit entries. (Sentry/OpenTelemetry: blocked until a DSN
or collector endpoint is provided.)

## 2. Release and rollback

1. CI green on the release commit (API, web, E2E incl. accessibility, mobile, OpenAPI, Docker).
2. Run **Deploy** (GitHub Actions, manual) for `staging`; it publishes `ghcr.io/<repo>/{api,web}:<sha>`.
3. Back up (section 5), then run migrations once: `php artisan migrate --force` and
   `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (idempotent).
4. Roll out API, queue, scheduler and web containers; wait for `/health/ready` = 200.
5. Smoke: sign in, search, open a listing, open `/admin/dashboard` as a broker.
6. Repeat for `production` after staging sign-off (environment reviewers enforce this).

**Rollback.** Redeploy the previous image tag. Migrations are additive within a release; if a
migration must be undone, `php artisan migrate:rollback --step=1 --force` only after confirming
in staging that its `down()` is safe for the data written since (CI runs up/down/up on every PR).
When in doubt restore from the pre-release backup into a new database and switch over (section 5).

## 3. Common incidents

**Queue backlog / `degraded` queue.** `php artisan queue:monitor redis:default,redis:notifications,redis:documents`;
check worker logs; scale `queue` replicas. Failed jobs: `php artisan queue:failed`, then
`php artisan queue:retry <id>` once the cause is fixed. Never `queue:flush` notifications without
deciding who must be re-notified.

**Scheduler stopped (`scheduler` degraded).** Restart the `scheduler` container
(`php artisan schedule:work`). Missed runs are safe to resume: reminders, offer expiry, licence
expiry and saved-search alerts are idempotent (claim columns / once-per-period guards).

**MLS sync failing or stale.** `/admin/mls` shows the last error. Typical causes: expired
credentials (update the connection; values are encrypted, never logged), provider outage, rate
limiting. Trigger an incremental sync from the monitor after fixing. Never switch a connection to
production without written MLS authorization (`MLS_SYNC_ENABLED` stays `false` until then).

**Documents stuck "Checking for malware".** clamd unreachable: `scan_status` stays `pending`, jobs
retry with backoff (30 s → 30 min) and end as `error` (never "clean"). Fix clamd, then re-dispatch:
`php artisan tinker --execute 'App\Models\DocumentVersion::whereIn("scan_status",["pending","error"])->each(fn($v)=>App\Jobs\ScanDocumentVersion::dispatch($v));'`.

**Suspected account compromise.** In `/admin/users`: deactivate the user (revokes tokens and
sessions). The user can also sign out other sessions from settings. Review `/admin/audit`
(category *security*) for the account and IPs. Reset MFA by disabling it on the account and asking
the user to enrol again after identity verification.

**Leaked `APP_KEY`.** Rotating it invalidates encrypted MFA secrets, MLS credentials, unsubscribe
tokens and sessions. Plan a re-encryption job (decrypt with the old key, encrypt with the new) in a
maintenance window; until then treat MLS credentials as compromised and rotate them with the MLS.

**Data-subject request (access/erasure).** Not automated yet (backlog CRM-8). Export the person's
records with the CSV exports and `/admin/audit`; erase via the lead/user screens; record the request
and outcome. Retention obligations (transaction documents) may override erasure — ask counsel.

## 4. Load testing

`infrastructure/load/search.js` (k6) drives the public search path: location autocomplete,
filtered search, listing detail. Thresholds: p95 < 500 ms search, < 400 ms detail, < 300 ms
autocomplete, > 99 % checks. Run against **staging** with production-like data and
`SSR_KEY=<API_SSR_KEY>` (so each virtual user gets its own client IP) — see the script header.

Smoke run on 2026-10-10 (5 VUs, 25 s, single-process `artisan serve`, 5 listings): all thresholds
passed, p95 101 ms search / 109 ms detail / 90 ms autocomplete, 0 errors. **This validates the
script, not capacity**: a capacity test on staging hardware with the real MLS inventory is still
pending (blocked on hosting).

## 5. Backup and restore

**Backups.** Managed PostgreSQL with point-in-time recovery is the primary mechanism (production
checklist). In addition, `infrastructure/scripts/backup.sh` writes a portable custom-format dump,
a documents archive and SHA-256 checksums; schedule it daily to encrypted off-site storage
(retention `BACKUP_RETENTION_DAYS`, default 30). Backups contain personal data.

**Restore** (always into a *new* database, then switch):

```bash
PGHOST=… PGUSER=app_inmobiliaria PGPASSWORD=… PG_ADMIN_USER=<superuser> PG_ADMIN_PASSWORD=… \
  infrastructure/scripts/restore.sh backups/app-inmobiliaria-db-<stamp>.dump app_inmobiliaria_restored
tar -C <documents-volume> -xzf backups/app-inmobiliaria-documents-<stamp>.tar.gz
```

The script verifies the checksum, refuses to overwrite an existing database, creates PostGIS and
btree_gist with the admin role (managed services: `rds_superuser`/`cloudsqlsuperuser`), restores
everything else as the app role and prints row counts. Point `DB_DATABASE` at the restored database
in a maintenance window and check `/health/ready`.

**Drill record.**

| Date | Source | Result |
|---|---|---|
| 2026-10-10 | Local `app_inmobiliaria` (17 migrations, 92 users, 5 listings, 961 audit entries) | Backup 0.3 s / 416 KB; restore 1 s; row counts identical; geography, `appointments_no_overlap` and PostGIS present; app read the copy (`migrate:status`, published listings). Found and fixed: PostGIS requires an admin role (script now creates extensions first and skips extension-owned objects). |

Repeat quarterly and after major schema changes, against a production-sized copy.

## 6. Security scanning and accessibility

* **ZAP baseline** — `.github/workflows/security.yml`, weekly and on demand, against the full stack
  with fictitious data; accepted findings and reasons are in `.zap/rules.tsv`; anything new fails.
  The first local run (2026-10-10) found personal data in URLs when a form was submitted without
  JavaScript (fixed: all JS forms post) and missing API hardening headers (fixed).
* **Dependency audits** — `composer audit` and `npm audit --omit=dev` run on every CI build.
* **Accessibility** — `tests/e2e/accessibility.spec.ts` runs axe-core (WCAG 2.1 A/AA) on public,
  buyer and staff pages in CI; see `docs/security/accessibility.md` for the manual checks.
* **Penetration test** — not yet performed; required before handling production client data.
