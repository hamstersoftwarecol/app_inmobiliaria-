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

## Sprint 8 — Showings & notifications (delivered)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| SHW-1 | Agent weekly hours + time off; public slots per listing (DST-safe, notice and horizon limits) | P0 | ✅ | `ShowingsTest`, `/dashboard/availability` |
| SHW-2 | Request → confirm/decline → reschedule/cancel → outcome + feedback, linked to the CRM lead timeline | P0 | ✅ | `ShowingsTest`, `/dashboard/showings`, E2E `showings.spec.ts` |
| SHW-3 | No double-booking enforced by a PostgreSQL exclusion constraint; `Idempotency-Key` on requests/bookings | P0 | ✅ | `ShowingsTest` |
| SHW-4 | Reminders 24 h and 1 h before; .ics invite on confirmation | P1 | ✅ | `ShowingsTest` |
| NTF-1 | In-app notification center (list, unread badge, mark read) | P1 | ✅ | `NotificationCenterTest`, header bell |
| NTF-2 | Per-category e-mail preferences, marketing opt-in with consent record, one-click unsubscribe (RFC 8058) | P1 | ✅ | `NotificationCenterTest`, settings, `/unsubscribe` |
| NTF-3 | Real-time delivery (Laravel Reverb) | P2 | ⏳ | Badge polls every minute meanwhile |
| NTF-4 | Push notifications (FCM) | P1 | ⛔ | Needs a Firebase project |
| NTF-5 | SMS (e.g. reminders) | P2 | ⛔ | Needs an SMS provider and TCPA-compliant consent flow |
| SHW-5 | Open houses (public events) | P2 | ⏳ | |

## Sprint 9 — Offers, transactions, documents, messaging (delivered)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| DEAL-1 | Offer state machine (draft → submitted → countered/accepted/rejected/withdrawn/expired), counter-offer chain, audited transitions, `Idempotency-Key` | P0 | ✅ | `OffersAndTransactionsTest`, `/dashboard/offers`, E2E `deals.spec.ts` |
| DEAL-2 | Accepting opens one transaction per property (partial unique index) and puts internal listings under contract | P0 | ✅ | `OffersAndTransactionsTest` |
| DEAL-3 | Transactions: default NJ milestones (attorney review 3 business days, contingencies, closing), custom milestones, parties, close/cancel | P0 | ✅ | `OffersAndTransactionsTest`, `/dashboard/transactions` |
| DEAL-4 | Milestone reminders (2 agency days before, once per due date); offers expire at their deadline | P1 | ✅ | `OffersAndTransactionsTest` |
| DOC-1 | Private, versioned documents (PDF/JPG/PNG/DOCX/XLSX ≤25 MB), internal vs shared with clients, SHA-256 | P0 | ✅ | `DocumentsTest` |
| DOC-2 | Short-lived signed downloads (5 min), access log (upload, link, download), audited visibility changes | P0 | ✅ | `DocumentsTest`, E2E `deals.spec.ts` |
| DOC-3 | Malware scanning with ClamAV (`clamd` INSTREAM); files downloadable only after a clean scan | P0 | ⛔ | Adapter + tests done; **blocked** until a clamd service is deployed and `DOCUMENT_SCANNER=clamd` is set (Compose profile `antivirus` provided) |
| DOC-4 | Retention: date set on close/cancel (6 years, configurable); purge command (dry run unless `--force`) | P1 | ⏳ | Period to be confirmed by counsel; purge intentionally not scheduled |
| DOC-5 | Documents on Cloudflare R2 (private bucket) | P1 | ⛔ | Needs the bucket and credentials; local private disk meanwhile |
| MSG-1 | Private conversations (participants only, no admin override), clients → active agents only, deal conversations | P0 | ✅ | `MessagingTest`, `/dashboard/messages`, E2E `deals.spec.ts` |
| MSG-2 | New-message notifications (in-app always; e-mail at most hourly per conversation, without the text) | P1 | ✅ | `MessagingTest` |
| MSG-3 | Real-time chat (Laravel Reverb) and attachments | P2 | ⏳ | Threads poll every 15 s meanwhile |
| DEAL-5 | E-signature integration | P2 | ⛔ | Needs a provider decision and contract (e.g. DocuSign) |

Offers are records for the agents' workflow; the binding contract is the signed agreement reviewed
by the parties' attorneys (stated in the UI).

## Sprint 10 — Mobile feature parity (delivered)

| ID | Item | Pri | Status | Evidence |
|---|---|---|---|---|
| MOB-1 | Search with city/ZIP autocomplete, filters (type, price, beds, baths), sorting, infinite scroll; `X-Hitlease-Client: mobile` so MLS mobile display rules apply | P0 | ✅ | `search_screen_test`, `repositories_test` |
| MOB-2 | Listing detail: gallery, facts, MLS attribution/disclaimer, agent contact (call/e-mail), favorites | P0 | ✅ | `search_screen_test` |
| MOB-3 | Favorites and saved searches (create from a search, alert frequency, replay, delete) | P0 | ✅ | `search_filters_test` |
| MOB-4 | Showing requests (slots, consent, `Idempotency-Key`), my showings; agents confirm/decline/cancel | P0 | ✅ | `repositories_test` |
| MOB-5 | Notification center with unread badge (polled) and deep links | P1 | ✅ | `app_notification_test`, `home_screen_test` |
| MOB-6 | Messages: inbox, threads, "Message the agent", deal conversations | P0 | ✅ | `conversation_screen_test` |
| MOB-7 | Agent mode: leads (filter by stage, status changes, activity log), tasks, transactions (milestones, signed document downloads) | P1 | ✅ | `home_screen_test` |
| MOB-8 | Map search (Mapbox SDK) | P1 | ⛔ | Needs a Mapbox access token; the Map tab says so |
| MOB-9 | Push notifications (FCM/APNs) | P1 | ⛔ | Needs a Firebase project and APNs key |
| MOB-10 | Offline cache | P2 | ⏳ | Not built: only data the MLS licences allow may be cached; decide per licence |
| MOB-11 | Store release (signing, listings, privacy labels) | P1 | ⛔ | Needs developer accounts and legal texts |

Uploads, offers and admin remain web-only for now (the app links to them).

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
