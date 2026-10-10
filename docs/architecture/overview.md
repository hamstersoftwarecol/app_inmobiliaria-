# Hitlease Real Estate — Architecture Overview

> Status: Phase 1 (analysis & architecture) — living document.
> Scope: brokerage platform for a New Jersey real estate agency (web + mobile + REST API + MLS integration engine).

## 1. Context

Hitlease Real Estate is a brokerage platform that lets one agency (multi-office capable) manage its inventory,
agents, leads, showings, offers, transactions and documents, and that publishes listings received from **authorized**
MLS feeds (NJMLS, Garden State MLS, Bright MLS and other RESO Web API–compatible providers) under each provider's IDX
rules.

```
              ┌───────────────────────────┐        ┌───────────────────────────┐
  Buyers  ───▶│  Web (Next.js, SSR/ISR)   │        │  Mobile (Flutter)         │◀─── Buyers / Agents
  Agents  ───▶│  apps/web                 │        │  apps/mobile              │
  Admins  ───▶└────────────┬──────────────┘        └─────────────┬─────────────┘
                           │ HTTPS (cookie session + CSRF)        │ HTTPS (Bearer token, secure storage)
                           ▼                                      ▼
              ┌──────────────────────────────────────────────────────────────────┐
              │                 Laravel REST API  (apps/api)  /api/v1             │
              │  Http ─▶ Application (use cases) ─▶ Domain ◀─ Infrastructure      │
              │  Sanctum · Policies/RBAC · Rate limiting · Audit · OpenAPI 3.1    │
              └───────┬───────────────┬──────────────┬───────────────┬───────────┘
                      │               │              │               │
              ┌───────▼──────┐ ┌──────▼─────┐ ┌──────▼──────┐ ┌──────▼────────────┐
              │ PostgreSQL 16│ │   Redis    │ │ Queue worker│ │ Cloudflare R2 (S3)│
              │  + PostGIS   │ │ cache/queue│ │ + scheduler │ │ media & documents │
              └──────────────┘ └────────────┘ └──────┬──────┘ └───────────────────┘
                                                     │ (only with signed agreements + credentials)
                                              ┌──────▼──────────────────────────┐
                                              │ MLS providers (RESO Web API...) │
                                              └─────────────────────────────────┘
```

Key principle: **all business data flows through the Laravel API**. Web and mobile clients never talk to MLS
providers, never hold MLS credentials and never compute authorization decisions on their own.

## 2. Architectural style

* **Modular monolith** for the API. A single deployable keeps operations simple for one agency, while module
  boundaries (Identity, Agency, Listings, MLS, Search, CRM, Scheduling, Transactions, Documents, Messaging,
  Notifications, Audit) allow later extraction (e.g. the MLS sync workers) if load requires it.
* **Clean Architecture layering** inside `apps/api/app`:

| Layer | Folder | Responsibility | May depend on |
|---|---|---|---|
| Domain | `app/Domain/<Module>` | Enums, value objects, domain contracts (interfaces), domain exceptions. Framework-light. | nothing outside Domain |
| Application | `app/Application/<Module>` | Use cases / services orchestrating domain + repositories, DTOs. | Domain |
| Infrastructure | `app/Infrastructure/<Module>` | Implementations of contracts: MLS adapters, HTTP clients, storage, external services. | Domain, Application |
| Http | `app/Http` | Controllers (thin), FormRequests (validation), API Resources (serialization), middleware. | Application, Domain |
| Policies | `app/Policies` | Per-resource authorization (Laravel Policies) backed by RBAC permissions. | Domain |
| Jobs | `app/Jobs` | Queue entry points (MLS sync, notifications). | Application |

Eloquent models live in `app/Models` (Laravel convention) and are treated as persistence models used by the
Application layer. We deliberately do **not** introduce a repository abstraction for every model (KISS); a
repository/contract is introduced only where an external system or swappable implementation exists (MLS providers,
licence verification, AI providers, antivirus scanning, storage).

* **Next.js App Router** web app: Server Components for public SEO pages (listings, city pages, agent profiles),
  Client Components for interactive areas (search map, dashboard, CRM board). TanStack Query handles API state,
  Zustand only for UI state (filters panel, map viewport).
* **Flutter** app: feature-first folders (`lib/features/<feature>/{data,domain,presentation}`), Riverpod for DI/state,
  GoRouter for navigation with auth redirect, Dio with interceptors for auth/errors.

## 3. Authentication model

| Client | Mechanism | Storage | Notes |
|---|---|---|---|
| Web (first-party, same site) | Sanctum SPA cookie session | HttpOnly, Secure, SameSite=Lax cookies | `GET /sanctum/csrf-cookie` then `X-XSRF-TOKEN`. No tokens in `localStorage`. |
| Mobile | Sanctum personal access tokens (`POST /api/v1/auth/token`) | `flutter_secure_storage` (Keychain / Keystore) | Expiration configurable (`SANCTUM_TOKEN_EXPIRATION`), named per device, revocable from session management. |
| Staff (admin, broker, office manager) | + TOTP MFA (RFC 6238) | secret encrypted at rest | MFA enforced by `mfa` middleware on staff-only routes once enrolled; enrolment required by policy setting. |

## 4. Authorization model (RBAC + Policies)

* Roles: `super_admin`, `broker`, `office_manager`, `agent`, `buyer` (buyer/tenant), `seller` (seller/owner).
* Permissions are granular strings (`properties.create`, `mls.manage`, `leads.view_any`...), stored in
  `permissions` and attached to roles (`permission_role`); users get roles via `role_user`.
* `super_admin` bypasses permission checks via `Gate::before` (still audited).
* **Every** resource access is checked in the backend with Policies that combine permission + ownership/assignment
  (e.g. an agent may update a property only if `properties.update_any` or they are its listing agent).
* The full matrix lives in [`docs/security/security-plan.md`](../security/security-plan.md#rbac-matrix) and in code in
  `App\Domain\Identity\Enums\Permission` + `database/seeders/RolesAndPermissionsSeeder.php` (single source of truth).

## 5. Data & storage

* PostgreSQL 16 + PostGIS 3: `geography(Point,4326)` column on properties with GiST index; bbox / radius /
  polygon search uses `ST_Intersects` / `ST_DWithin`. See [`docs/database/erd.md`](../database/erd.md).
* MLS listings are keyed by `(mls_provider_id, listing_key)` — never by bare `ListingId` — to avoid cross-provider
  collisions.
* Redis: cache (short TTL, respecting MLS freshness rules), queues, rate-limiter, Reverb scaling.
* Cloudflare R2 (S3 API) for media and private documents. Documents are never public; downloads use short-lived
  signed URLs after a policy check, and each access is logged.

## 6. Asynchronous work

Laravel Queues (Redis) with dedicated queues: `default`, `notifications`, `mls-sync`, `mls-media`, `documents`.
Scheduler runs incremental MLS syncs (per-provider frequency from `mls_display_rules`), saved-search alerts,
showing reminders and retention purges.

## 7. Cross-cutting concerns

| Concern | Approach |
|---|---|
| Errors | Single JSON error envelope rendered in `bootstrap/app.php` (`{"error":{"code","message","details"}}`), no stack traces outside `local`. |
| Validation | FormRequests on the API (authoritative) + Zod (web) + form validators (mobile) for UX. |
| Audit | `audit_logs` written by `AuditLogger` for security events and critical changes (who, what, before/after, IP, UA). |
| Rate limiting | Named limiters: `api`, `auth` (login/register/forgot), `search`. |
| Idempotency | `Idempotency-Key` header honoured by `idempotent` middleware for critical POSTs (offers, appointments, transactions). |
| Observability | Sentry (errors) + OpenTelemetry (traces/metrics) — wired via env, disabled when DSN is absent. |
| i18n | English primary, Spanish secondary on web and mobile; API messages in English with stable error codes. |
| Time zones | Persist UTC (`timestamptz`); agency default zone `America/New_York`; convert at the edges (DST-safe via IANA zones). |

## 8. Decisions log

ADRs live in [`docs/architecture/adr`](adr). Current decisions:

* [ADR-0001](adr/0001-modular-monolith.md) — Modular monolith with Clean Architecture layers.
* [ADR-0002](adr/0002-auth-cookie-web-token-mobile.md) — Sanctum cookies for web, tokens for mobile.
* [ADR-0003](adr/0003-custom-rbac.md) — In-house RBAC tables instead of a third-party package.
* [ADR-0004](adr/0004-mls-adapter-contract.md) — MLS provider adapter contract, disabled by default.

## 9. Related documents

* [Components](components.md) · [ERD](../database/erd.md) · [OpenAPI](../api/openapi.yaml)
* [MLS integration plan](../mls/integration-plan.md) · [Security plan](../security/security-plan.md)
* [Product backlog](../product-backlog.md) · [Deployment](../deployment/README.md)
