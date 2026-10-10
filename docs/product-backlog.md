# Product Backlog

Two-week sprints. Priority: **P0** (blocking MVP) · **P1** (MVP) · **P2** (post-MVP).
Status: ✅ done (implemented, tested, documented) · 🟡 partial · ⏳ to do · ⛔ blocked by external
dependency (not counted as done).

## Sprint 1 — Architecture, repository, authentication (this delivery)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| ARC-1 | Architecture, components, ERD, OpenAPI 3.1, MLS plan, security plan, backlog | P0 | ✅ | `docs/` |
| ARC-2 | Monorepo: Laravel 13 API, Next.js 15 web, Flutter 3.47 app | P0 | ✅ | `apps/*` |
| ARC-3 | PostgreSQL 16 + PostGIS 3, Redis | P0 | ✅ | migrations, `docker-compose.yml` |
| ARC-4 | Docker images + Compose (API, queue, scheduler, web, nginx, Postgres, Redis) | P0 | 🟡 | Web image built & run locally; API image build not verifiable in the dev sandbox (Alpine package mirror blocked) — verified by CI `docker` job |
| ARC-5 | CI: lint, static checks, tests, migrations up/down, audits, E2E, Flutter, OpenAPI lint, Docker builds | P0 | ✅ | `.github/workflows/ci.yml` (first run happens on push) |
| ARC-6 | Controlled deploy workflow (GHCR images, environment approvals) | P1 | 🟡 | Rollout step pending hosting decision |
| AUTH-1 | Register / login (cookie + token) / logout / me | P0 | ✅ | API tests, web E2E, mobile tests |
| AUTH-2 | Password reset, e-mail verification | P0 | ✅ | `PasswordAndVerificationTest` |
| AUTH-3 | TOTP MFA + recovery codes; staff MFA enforcement | P0 | ✅ | `ProfileAndMfaTest`, `TokenLoginTest`, `SessionLoginTest` |
| AUTH-4 | Brute-force protection, session/token management, security activity, self-deactivation | P0 | ✅ | |
| RBAC-1 | Roles, permissions, policies, hierarchy guards, admin users/roles API | P0 | ✅ | `UserAdministrationTest`, `RolesAndAuditTest` |
| AUD-1 | Append-only audit log with redaction + admin endpoint | P0 | ✅ | |
| MLS-1 | MLS contract, DTOs, RESO adapter, normalization, compliance, licence gate, admin endpoints | P0 | ✅ | `tests/Feature/Mls`, `tests/Unit/Mls` |
| MLS-2 | NJMLS / GSMLS / Bright adapters verified against official specs | P0 | ⛔ | Needs licences + specs + sandbox credentials |
| WEB-1 | Web shell, bilingual UI, auth screens, dashboard, settings (MFA, sessions), admin users/roles | P0 | ✅ | Vitest + Playwright |
| MOB-1 | Mobile shell, token auth, secure storage, bilingual, bottom navigation | P0 | ✅ | `flutter test` |

## Sprint 2 — Users, agency and agents foundation

| ID | Item | Pri | Depends on |
|---|---|---|---|
| AGY-1 | `agencies`, `offices` + broker settings UI | P1 | RBAC-1 |
| AGT-1 | `agents`, `agent_licenses` (unverified → verified workflow, verifier + source recorded) | P0 | AGY-1 |
| AGT-2 | Public agent profiles (`/agents/[slug]`), agent admin screens | P1 | AGT-1 |
| ADM-1 | Web admin: create/edit users, assign roles, audit log viewer | P1 | RBAC-1 |
| SET-1 | `system_settings` (audited) | P2 | |

## Sprint 3–4 — MLS pipeline & properties

| ID | Item | Pri | Depends on |
|---|---|---|---|
| MLS-3 | `mls_listings`, `mls_field_mappings`, `mls_sync_jobs`, `mls_sync_logs`, `mls_licenses` | P0 | MLS-1 |
| MLS-4 | Jobs: Import, Sync (incremental cursor), SyncMedia, Reconcile; dedup service; monitoring panel | P0 | MLS-3 |
| MLS-5 | Production enablement | P0 | ⛔ MLS-2 |
| PRP-1 | `properties` + types/features/amenities/media/status history (PostGIS point, GiST) | P0 | ARC-3 |
| PRP-2 | Internal listing CRUD, publish/unpublish, agent assignment, ownership policies | P0 | PRP-1, AGT-1 |
| PRP-3 | Media upload to R2 (validation, optimization, ordering) | P1 | PRP-1 |
| PRP-4 | SEO pages, JSON-LD (`RealEstateListing`), sitemap | P1 | PRP-2 |

## Sprint 5–6 — Search, maps, details

| ID | Item | Pri |
|---|---|---|
| SRC-1 | `/search/properties` filters, sort, pagination; `/search/locations` autocomplete | P0 |
| SRC-2 | Bounding box, radius (`ST_DWithin`), polygon (`ST_Intersects`) search | P0 |
| SRC-3 | Saved searches + new-listing alerts | P1 |
| MAP-1 | Mapbox GL map: price markers, clustering, search-as-you-move, draw polygon | P0 |
| DET-1 | Property detail page + gallery + MLS attribution/disclaimer | P0 |

## Sprint 7 — CRM

Leads (sources, assignment, duplicates), Kanban pipeline, activities (calls, e-mails, notes),
tasks & reminders, reports. Policies: `leads.view_assigned` vs `leads.view_any`.

## Sprint 8 — Showings & notifications

Availability calendar, request → confirm → reminder → feedback flow, reschedule/cancel,
DST-correct time zones, no-overlap exclusion constraint, `Idempotency-Key`; notification service
(mail, in-app, push via FCM, optional SMS) with preferences and consent; Reverb for real time.

## Sprint 9 — Offers, transactions, documents, messaging

State machines (offer & transaction), contingencies, milestones, tasks; private documents with
versions, signed URLs, access logs, AV scanning, retention; client–agent chat with authorization
per conversation.

## Sprint 10 — Mobile feature parity

Search, map (Mapbox SDK — needs token), details, favorites, saved searches, showings,
messages, notifications (FCM — needs Firebase project), agent mode (dashboard, leads, pipeline,
tasks, transactions). Offline cache only for data the MLS licence allows.

## Sprint 11 — Admin dashboard & reports

KPIs (listings, agents, leads, showings, offers, transactions, conversion), Recharts charts,
exports, audit viewer, MLS monitoring.

## Sprint 12 — Hardening & release candidate

Load tests on search, accessibility audit (WCAG 2.1 AA), ZAP baseline, backup/restore drill,
observability (Sentry + OpenTelemetry), runbooks, MLS rule validation with each provider.

## AI module (post-MVP, P2)

NL search → structured filters (validated against the same filter schema; never invents
listings, prices or availability); agent writing aids for **own** listings only; no MLS data to AI
providers unless `allow_ai_processing` is granted by the licence; no protected attributes; human
review for business decisions.

## External dependencies / blockers

| Dependency | Needed for | Owner |
|---|---|---|
| MLS data licences, IDX/VOW rules, API specs, sandbox credentials | MLS-2, MLS-5 | Broker |
| Mapbox access token | MAP-1, mobile map | Agency |
| Firebase project (FCM) | Push notifications | Agency |
| Cloudflare R2 bucket + credentials | Media, documents | DevOps |
| Mail provider (SPF/DKIM/DMARC) | Production e-mail | DevOps |
| Hosting target (web SSR + PHP-FPM) | Automated rollout | DevOps |
| Brokerage licence data & legal copy | Footer/advertising compliance | Broker / counsel |
