# Hitlease Real Estate

Brokerage platform for a New Jersey real estate agency: listings (own + authorized MLS feeds),
agents, CRM, showings, offers, transactions and documents — with a web app, Android/iOS apps and a
central REST API.

| App | Stack | Path |
|---|---|---|
| REST API | Laravel 13 · PHP 8.3 · Sanctum · PostgreSQL 16 + PostGIS · Redis | [`apps/api`](apps/api) |
| Web | Next.js 15 · TypeScript · Tailwind 4 · shadcn/ui · TanStack Query · Zustand · RHF + Zod | [`apps/web`](apps/web) |
| Mobile | Flutter 3.47 · Riverpod · GoRouter · Dio · Freezed · secure storage | [`apps/mobile`](apps/mobile) |

## Status (Sprint 1)

**Done and tested:** architecture & docs, monorepo, Docker/Compose, CI; authentication (cookie for
web, tokens for mobile), TOTP MFA with recovery codes, password reset, e-mail verification,
session management, brute-force protection; RBAC (6 roles, 49 permissions, hierarchy guards);
audit log; admin API for users/roles/audit/MLS providers; agencies, offices, agent profiles, public agent directory and licence verification workflow (Sprint 2); MLS engine contract with a RESO Web API
adapter, normalization and compliance rules; web and mobile auth flows.

**Blocked by external authorization:** real MLS connections (no licences/specs/credentials yet).
**Not built yet:** properties, search, maps, CRM, showings, offers, documents, messaging — see
[`docs/product-backlog.md`](docs/product-backlog.md). The UI labels these as "in development";
no listings are invented.

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
* [Product backlog](docs/product-backlog.md) · [Deployment](docs/deployment/README.md)

## Rules of the road

No secrets in the repository · no scraping of third-party portals · MLS data only under signed
licences and per-provider rules · backend authorizes every request · features are marked done
only with tests and docs (see [`AGENTS.md`](AGENTS.md)).
