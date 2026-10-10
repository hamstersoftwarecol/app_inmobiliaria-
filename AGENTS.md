# Guide for contributors and coding agents

## Repository map
* `apps/api` — Laravel API. Layers: `app/Domain` (enums, contracts, DTOs, exceptions),
  `app/Application` (use cases/services), `app/Infrastructure` (adapters), `app/Http`
  (thin controllers, FormRequests, Resources, middleware), `app/Policies`.
* `apps/web` — Next.js. Business data only through `src/services/*` → `src/lib/api-client.ts`.
* `apps/mobile` — Flutter, feature-first (`data/domain/application/presentation`).
* `docs/` — architecture, ERD, OpenAPI, MLS, security, backlog, deployment. Keep them current.

## Non-negotiable rules
1. Never commit secrets; only `.env.example` files. MLS credentials live encrypted in the DB.
2. Never invent MLS endpoints, credentials or authorizations; never scrape portals.
3. Authorization is enforced in the API (Policies/Gates with `Permission` enum cases). Client
   checks are UX only.
4. New permissions: add a case to `App\Domain\Identity\Enums\Permission`, map it in
   `RoleName::defaultPermissions()`, update the RBAC matrix in `docs/security/security-plan.md`.
5. All API errors use the envelope rendered by `App\Http\ApiErrorRenderer`; throw
   `DomainException` subclasses with a stable `errorCode` for business rules.
6. Security-relevant and administrative changes are written with `AuditLogger` (never log secrets).
7. Store timestamps in UTC (`timestamptz`); convert with the user's/agency's IANA zone.
8. Demo/fixture data only in local/testing (`DevelopmentSeeder`, `FixtureMLSProvider` enforce this).
9. A feature is "done" only with validation, permissions, tests, error handling and docs. Work that
   depends on an unavailable external service is marked **blocked**, not done.
10. Update `docs/api/openapi.yaml` with every endpoint change (CI lints it).

## Commands
| | Command |
|---|---|
| API tests | `cd apps/api && php artisan test` (PostgreSQL+PostGIS `app_inmobiliaria_test`) |
| API style | `cd apps/api && vendor/bin/pint` |
| Web | `cd apps/web && npm run lint && npm run typecheck && npm test` |
| Web E2E | `cd apps/web && npm run build && npm run test:e2e` (API running on :8000) |
| Mobile | `cd apps/mobile && dart run build_runner build -d && flutter analyze && flutter test` |
| Contract | `npx @redocly/cli lint docs/api/openapi.yaml --config docs/api/redocly.yaml` |
