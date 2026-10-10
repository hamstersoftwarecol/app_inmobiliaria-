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

## Sprint 2 — Agency, offices, agents and licences (delivered)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| AGY-1 | `agencies`, `offices` (PostGIS location, one HQ per agency) + admin API and UI | P1 | ✅ | `AgencyAndOfficeTest`, `/admin/agency` |
| AGT-1 | `agents`, `agent_licenses`; licence workflow pending → verified/rejected → expired/revoked with source, reference, expiry, separation of duties, daily expiry job, notifications, audit | P0 | ✅ | `LicenseVerificationTest`, `/admin/licenses`, E2E `agents.spec.ts` |
| AGT-2 | Public agent directory + profile pages (SSR, JSON-LD), agent self-service profile & licence submission, admin agent screens | P1 | ✅ | `PublicDirectoryTest`, `AgentAdministrationTest`, Vitest, Playwright |
| AGT-3 | Agent photo upload | P2 | ⛔ | Needs Cloudflare R2 bucket (PRP-3); `photo_url` accepts an HTTPS URL meanwhile |
| AGT-4 | Automated licence lookup against the NJ Real Estate Commission | P2 | ⛔ | No official API/authorization known; verification is a manual, recorded staff step |
| AGT-5 | Mobile agent directory / agent profile screens | P1 | ⏳ | Moved to Sprint 10 (mobile parity) |
| ADM-1 | Web admin: create/edit users, audit log viewer | P1 | ✅ | Delivered in Sprint 4 |
| SET-1 | `system_settings` (audited) | P2 | ⏳ | |

## Sprint 3 — MLS pipeline & properties (delivered)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| MLS-3 | `mls_listings` (unique provider + ListingKey), `mls_field_mappings`, `mls_sync_jobs`, `mls_sync_logs` | P0 | ✅ | migrations `2026_03_01_*`, `MlsSyncPipelineTest` |
| MLS-4 | Jobs: Import, Sync (incremental cursor), SyncMedia, Reconcile (with removal safety threshold); content-hash dedup + cross-MLS duplicate candidates; scheduler; admin sync API and monitoring panel | P0 | ✅ | `MlsSyncPipelineTest`, `/admin/mls` |
| MLS-5 | Production enablement | P0 | ⛔ | Needs MLS-2 (licences, specs, credentials) |
| MLS-6 | `mls_licenses` (licence documents, scope, expiry) | P1 | ⏳ | Deferred until the first agreement is signed; the licence reference is recorded on the provider meanwhile |
| PRP-1 | `properties` (PostGIS point + GiST, partial public index), types, amenities, media, append-only status/price history | P0 | ✅ | `PropertyManagementTest` |
| PRP-2 | Internal listing CRUD, publish/unpublish with completeness check, agent assignment, ownership policies, MLS listings read-only | P0 | ✅ | `PropertyManagementTest`, `/dashboard/properties`, E2E `properties.spec.ts` |
| PRP-3 | Photo upload: type/size/dimension validation, re-encoding (EXIF/GPS stripped), ordering | P1 | 🟡 | `PropertyMediaTest`; stored on the `public` disk — Cloudflare R2 storage ⛔ (bucket + credentials + `league/flysystem-aws-s3-v3`) |
| PRP-4 | Public listing pages (SSR), JSON-LD `RealEstateListing`, sitemap entry, MLS attribution/disclaimer at render time | P1 | ✅ | `PublicPropertyComplianceTest`, Vitest, Playwright |
| PRP-5 | Mobile listing screens | P1 | ⏳ | Sprint 10 (mobile parity) |
| ADM-1 | Web admin: create/edit users, audit log viewer | P1 | ✅ | Delivered in Sprint 4 |

## Sprint 4 — Search, favorites, saved searches (delivered)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| SRC-1 | `GET /properties` filters (type, city, county, ZIP, price, beds, baths, size, year, amenities), sorts, pagination; `GET /locations` autocomplete | P0 | ✅ | `PropertySearchTest`, `/properties` |
| SRC-2 | Bounding box (`ST_MakeEnvelope`), radius (`ST_DWithin`), polygon (`ST_Intersects`) on the GiST-indexed location; only listings with a public address | P0 | ✅ | `PropertySearchTest`; web: "near me" and radius from a suggested city/ZIP |
| SRC-3 | Saved searches + daily/weekly new-listing alerts (mail to verified addresses + in-app) | P1 | ✅ | `FavoritesAndSavedSearchesTest`, `/dashboard/saved-searches` |
| SRC-4 | Favorites | P1 | ✅ | `FavoritesAndSavedSearchesTest`, `/dashboard/favorites`, heart on cards and detail |
| SRC-5 | Per-visitor rate limits for server-rendered pages (`API_SSR_KEY`) | P0 | ✅ | `PropertySearchTest` |
| ADM-1 | Web admin: create/edit users and roles (hierarchy-aware), audit log viewer with agency-day date filters | P1 | ✅ | `RolesAndAuditTest`, Vitest, E2E |
| MAP-1 | Mapbox GL map: price markers, clustering, search-as-you-move, draw polygon | P0 | ⛔ | Needs a Mapbox access token; the API side (bbox/polygon) is ready |
| SRC-6 | `property_views` analytics | P2 | ⏳ | Sprint 11 (reports) |

## Sprint 7 — CRM (delivered; numbered after the Sprint 5–6 plan, which was merged into Sprint 4)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| CRM-1 | Public inquiries (listing, agent page, general) with consent capture, honeypot, rate limit, auto-assignment to the listing/profile agent, open-lead dedup | P0 | ✅ | `LeadPipelineTest`, contact forms, E2E `crm.spec.ts` |
| CRM-2 | Leads: visibility (`view_any` / `view_assigned`), manual entry, edit, stages with lost reason, assignment (audited, notified), merge duplicates, soft delete (`leads.delete`, audited) | P0 | ✅ | `LeadPipelineTest`, `/dashboard/crm` |
| CRM-3 | Kanban board and list with filters; accessible "move to" control instead of drag-only | P0 | ✅ | `/dashboard/crm` |
| CRM-4 | Append-only timeline (calls, e-mails, meetings, notes + system events); first-contact tracking | P0 | ✅ | `LeadPipelineTest` |
| CRM-5 | Tasks with due dates and reminders (15 min before, once per due time) | P1 | ✅ | `TasksAndReportsTest`, `/dashboard/crm/tasks` |
| CRM-6 | Reports: totals, conversion, median first response, by stage/source/agent (agency days) | P1 | ✅ | `TasksAndReportsTest`, `/dashboard/crm/reports` |
| CRM-7 | Configurable pipelines/stages per agency | P2 | ⏳ | Fixed stages for now (`LeadStatus`) |
| CRM-8 | Data-subject requests (export/erase a lead's personal data) and retention schedule | P1 | ⏳ | Needs the agency's privacy policy |
| CRM-9 | Mobile CRM (agent mode) | P1 | ⏳ | Sprint 10 |

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
| Mapbox access token | MAP-1 (web map), mobile map | Agency |
| Firebase project (FCM) | Push notifications | Agency |
| Cloudflare R2 bucket + credentials | Media (PRP-3), documents, agent photos | DevOps |
| Mail provider (SPF/DKIM/DMARC) | Production e-mail | DevOps |
| Hosting target (web SSR + PHP-FPM) | Automated rollout | DevOps |
| Brokerage licence data & legal copy | Footer/advertising compliance | Broker / counsel |
