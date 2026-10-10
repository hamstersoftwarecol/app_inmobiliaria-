# Hitlease Real Estate

Brokerage platform for a New Jersey real estate agency: listings (own + authorized MLS feeds),
agents, CRM, showings, offers, transactions and documents — with a web app, Android/iOS apps and a
central REST API.

| App | Stack | Path |
|---|---|---|
| REST API | Laravel 13 · PHP 8.3 · Sanctum · PostgreSQL 16 + PostGIS · Redis | [`apps/api`](apps/api) |
| Web | Next.js 15 · TypeScript · Tailwind 4 · shadcn/ui · TanStack Query · Zustand · RHF + Zod | [`apps/web`](apps/web) |
| Mobile | Flutter 3.47 · Riverpod · GoRouter · Dio · Freezed · secure storage | [`apps/mobile`](apps/mobile) |

## Status — release candidate 1.0.0-rc.1 (Sprints 1–12)

**Done and tested (web + API + mobile):** authentication (cookie for web, tokens for mobile), TOTP
MFA, sessions, brute-force protection; RBAC (6 roles, 50 permissions) and audit log; agencies,
offices, agents and licence verification; MLS engine (RESO Web API adapter, normalization,
compliance rules, sync monitor); listings with photos and publication workflow; search (filters,
radius/bbox/polygon, sorting, autocomplete), favorites and saved-search alerts; CRM (inquiries,
leads pipeline, timeline, tasks, reports); showings (availability, DST-safe slots, no
double-booking) and notification center; offers, transactions with NJ milestones, private versioned
documents with signed downloads, private messaging; agency KPI dashboard with audited CSV exports.
The Flutter app covers search, listing detail, favorites, saved searches, showings, notifications,
messages and an agent mode. Hardening: accessibility checks (axe), ZAP baseline workflow, readiness
probe, load-test script, backup/restore with a recorded drill, runbooks — see
[`CHANGELOG.md`](CHANGELOG.md) and [`docs/operations/runbooks.md`](docs/operations/runbooks.md).

**Blocked by external authorization or infrastructure (not simulated):** real MLS connections
(licences, specs, credentials), maps (Mapbox token), push (Firebase), Cloudflare R2, deployed ClamAV,
error tracking (Sentry DSN), production mail and hosting, capacity and penetration tests, legal
review. Details in [`docs/product-backlog.md`](docs/product-backlog.md). This software is not
certified for any legal or regulatory purpose.

## Quick start

See [`docs/deployment/README.md`](docs/deployment/README.md) for local setup (with or without
Docker), tests and production settings.

```bash
infrastructure/scripts/check-all.sh   # lint + tests for all apps
```

## Documentation

* [Architecture overview](docs/architecture/overview.md) · [Components](docs/architecture/components.md) · [ADRs](docs/architecture/adr)
* [Database model](docs/database/erd.md) · [OpenAPI 3.1 contract](docs/api/openapi.yaml)
* [MLS integration plan](docs/mls/integration-plan.md) · [Security plan & RBAC matrix](docs/security/security-plan.md)
* [Product backlog](docs/product-backlog.md) · [Deployment](docs/deployment/README.md) · [Runbooks](docs/operations/runbooks.md) · [Accessibility](docs/security/accessibility.md) · [Changelog](CHANGELOG.md)

## Rules of the road

No secrets in the repository · no scraping of third-party portals · MLS data only under signed
licences and per-provider rules · backend authorizes every request · features are marked done
only with tests and docs (see [`AGENTS.md`](AGENTS.md)).
