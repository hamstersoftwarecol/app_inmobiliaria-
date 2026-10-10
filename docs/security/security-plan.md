# Security Plan

Aligned with OWASP ASVS 4.0 (Level 2 target) and OWASP API Security Top 10. This document is an
engineering plan, **not** a legal or compliance certification; a specialized review is required
before claiming compliance with any regulation.

## 1. Controls implemented in Sprint 1

| Area | Control | Where |
|---|---|---|
| Authentication | bcrypt passwords; policy ≥12 chars with upper/lower/number/symbol; breached-password check (HIBP k-anonymity) in production | `AppServiceProvider::configurePasswordPolicy` |
| | Web: Sanctum cookie session (HttpOnly, SameSite=Lax, Secure in prod, encrypted session) + CSRF; session id regenerated on login | `AuthController`, `config/session.php` |
| | Mobile: personal access tokens with expiry (`SANCTUM_TOKEN_EXPIRATION`), prefix `hls_` for secret scanning, stored in Keychain/Keystore | `AuthController::issueToken`, `SecureTokenStorage` |
| | Brute force: per-email+IP lockout (5 attempts / 15 min), per-IP limiter on credential endpoints, per-account MFA attempt lockout; uniform timing on unknown e-mails | `CredentialVerifier`, `RateLimiter::for('auth')` |
| | TOTP MFA (RFC 6238) with replay protection; secrets encrypted at rest; 8 single-use recovery codes stored as SHA-256 hashes; enforced for staff on `/admin` | `MfaService`, `RequireStaffMfa` |
| | Password reset: non-enumerating responses, expiring tokens, all sessions/tokens revoked after reset | `PasswordController` |
| | Signed, expiring e-mail verification links | `VerifyEmailNotification` |
| Session management | List/revoke browser sessions and tokens; revoke others on password change; deactivation revokes everything; `active` middleware blocks deactivated accounts holding old credentials | `SessionManager`, `EnsureUserIsActive` |
| Authorization | RBAC (6 roles, 50 permissions) + Policies; super-admin bypass is explicit; role hierarchy prevents escalation and peer management; checks always server-side | `RoleName`, `Permission`, `UserPolicy`, `UserAdministration` |
| Input validation | FormRequests on every write; enums/whitelists for sort and filters; e-mail normalization | `app/Http/Requests` |
| Output / errors | Uniform error envelope, no stack traces outside debug; request id for support | `ApiErrorRenderer` |
| Transport & headers | HSTS (when HTTPS), CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy (API and web) | `SecurityHeaders`, `apps/web/next.config.ts` |
| CORS | Explicit allow-list of origins, credentials only for those | `config/cors.php` |
| Secrets | No secrets in the repo (`.env*` ignored, only `.env.example`); MLS credentials encrypted in DB and never serialized; audit redaction of sensitive keys | `.gitignore`, `MlsConnection`, `AuditLogger` |
| Audit | Append-only `audit_logs` (model refuses update/delete) for security, admin and MLS events, with IP, UA, request id | `AuditLogger`, `AuditLog` |
| Outbound requests | MLS adapter: HTTPS only, paging links restricted to the configured host (SSRF/token leak guard); tests block stray HTTP | `RESOWebAPIProvider`, `tests/TestCase.php` |
| Supply chain | `composer audit`, `npm audit`, Dependabot, pinned major versions | CI, `.github/dependabot.yml` |
| Web client | No tokens in browser storage; open-redirect guard on `?next=`; client guards are UX only | `api-client.ts`, `login-form.tsx` |
| Mobile client | Token in secure storage; cleartext HTTP only in debug builds; 401 wipes the token | `AndroidManifest.xml`, `AuthInterceptor` |

## 2. RBAC matrix

Source of truth: `App\Domain\Identity\Enums\RoleName::defaultPermissions()`.

| Permission group | super_admin | broker | office_manager | agent | seller | buyer |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| users.* (view/create/update/deactivate/assign_roles) | ✔ | ✔ | view | – | – | – |
| roles.view / roles.manage | ✔ / ✔ | ✔ / – | – | – | – | – |
| audit_logs.view | ✔ | ✔ | – | – | – | – |
| settings.manage | ✔ | – | – | – | – | – |
| agency.manage, agents.view_any, agents.manage | ✔ | ✔ | agents.view_any | – | – | – |
| agents.verify_licenses (never on own licence) | ✔ | ✔ | – | – | – | – |
| agents.manage_own_profile | ✔ | ✔ | – | ✔ | – | – |
| properties.view_any / update_any / delete / publish | ✔ | ✔ | view_any | – | – | – |
| properties.create / update_own | ✔ | ✔ | – | ✔ | – | – |
| seller.listings.view | ✔ | ✔ | – | – | ✔ | – |
| mls.view / mls.manage / mls.sync | ✔ | view | – | – | – | – |
| leads.view_any / update_any / assign | ✔ | ✔ | ✔ | – | – | – |
| leads.view_assigned / update_assigned / create | ✔ | ✔ | create | ✔ | – | – |
| leads.delete | ✔ | ✔ | – | – | – | – |
| appointments.view_any / manage_any | ✔ | ✔ | ✔ | – | – | – |
| appointments.manage_own | ✔ | ✔ | – | ✔ | – | – |
| appointments.request | ✔ | ✔ | – | – | – | ✔ |
| offers / transactions view_any | ✔ | ✔ | ✔ | – | – | – |
| offers / transactions manage_any | ✔ | ✔ | – | – | – | – |
| offers / transactions manage_own | ✔ | ✔ | – | ✔ | – | – |
| documents.view_any / manage_any | ✔ | ✔ | ✔ | – | – | – |
| documents.manage_own | ✔ | ✔ | – | ✔ | – | – |
| documents.view_shared | ✔ | ✔ | – | – | ✔ | ✔ |
| messages.use | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| favorites.manage | ✔ | ✔ | – | – | ✔ | ✔ |
| saved_searches.manage | ✔ | ✔ | – | – | – | ✔ |
| reports.view / analytics.view | ✔ | ✔ | reports | – | – | – |

Role hierarchy (levels): super_admin 100 › broker 80 › office_manager 60 › agent 40 › seller 20 ›
buyer 10. Non-super-admins can only assign roles **below** their highest level and only manage
users strictly below it; nobody manages themselves through the admin API.

Permission edits by super admins are preserved across deployments (the seeder only applies
defaults when a role is first created; the super admin role always gets everything).

### Licence verification controls (Sprint 2)

* Licence numbers typed by agents or staff are stored as `pending_verification`; nothing is public
  until a verifier records the **source** (state licensee lookup, document review, regulator
  confirmation), a **reference** and the **expiration date**.
* Separation of duties: nobody (super admins included) can verify, reject or revoke their own
  licence; transitions run under a row lock and follow a fixed state machine.
* A partial unique index allows one active (pending/verified) record per licence number and state.
* A daily job (00:15 America/New_York) expires licences; expired ones are hidden from the public
  directory immediately by date even before the job runs.
* Every transition is audited and notified to the agent (mail + in-app).

### Listing and MLS display controls (Sprint 3)

* Agents edit only listings where they are the listing agent (`update_own`) and always list under
  their own profile; publishing and deleting are separate permissions (`properties.publish`,
  `properties.delete`). Publication requires a complete listing; incomplete edits unpublish it.
* MLS-sourced listings are read-only in the dashboard; publication is driven by the provider's
  display rules and status.
* Public endpoints re-check MLS rules on every request (provider enabled, public / mobile display,
  removal, allowed fields, address suppression, photo limits) and always return attribution,
  disclaimer and last-updated time; public pages are not served from the Next.js data cache, so
  withdrawals take effect immediately.
* Photo uploads: JPEG/PNG/WebP only, ≤ 15 MB, ≥ 640×480, decoded and re-encoded with GD — EXIF
  (including GPS location) and any embedded payload are discarded. In Compose, nginx serves only
  image extensions from the media volume and never executes files there.
* Sync, field-mapping changes, publication and deletions are written to the audit log.

### Search, favorites and saved searches (Sprint 4)

* Geographic filters (bbox, radius, polygon) only match listings whose exact address is public, so
  repeatedly narrowing an area cannot locate a listing with a suppressed address; autocomplete map
  centres are computed from public locations only.
* Favorites and saved-search alerts go through the same public-visibility rules as the search page:
  hidden, withdrawn or MLS-restricted listings are never returned or e-mailed. Saved searches are
  owner-scoped (other users' ids return 404); limits: 500 favorites, 25 saved searches.
* Alert e-mails go only to verified addresses, link to the page where alerts are turned off, and stop
  when the account is deactivated or loses `saved_searches.manage`.
* Rate limits: `search` 60/min and `api` 120/min per user or visitor. Server-side rendering sends the
  shared secret `API_SSR_KEY` with the visitor's IP; the API trusts the forwarded IP only when the
  secret matches (constant-time comparison), so browsers cannot spoof it. The secret is server-only.
* Admin user screens offer only roles below the operator's level; the API remains the authority
  (hierarchy guards). Audit-log date filters use agency calendar days.

### CRM and personal data (Sprint 7)

* Leads hold personal data: they are never returned by public endpoints, `/crm/*` requires MFA for
  staff, agents see and edit only leads assigned to their own profile, and merges/deletions and
  assignments are audited. Assignment e-mails contain the lead's name and a link only.
* Public inquiries require explicit consent to be contacted (stored with time and IP), keep
  marketing consent separate and optional, use a honeypot and per-visitor rate limits (5/min,
  20/h), and answer identically whether or not the person was already known.
* The timeline is append-only (model-level guard), so contact history cannot be rewritten.
* Pending: data-subject export/erasure and a retention schedule (CRM-8), once the agency's privacy
  policy is defined.
* New permissions are granted automatically to roles whose defaults include them when a release
  introduces them; existing customizations are preserved.

### Showings and notifications (Sprint 8)

* Overlapping showings for an agent are impossible at the database level (exclusion constraint),
  so concurrent requests for one slot fail cleanly (`slot_unavailable`) for all but one.
* Visitors can only book published slots; requesting requires sign-in, `appointments.request` and
  explicit consent to be contacted. Contact details, CRM link and feedback are visible only to the
  host and managers; the street address is shared with the visitor once the showing is confirmed
  (or when the listing already shows it).
* `Idempotency-Key` (per user, 24 h, request-hash checked) prevents duplicate bookings on retries;
  a reused key with a different body is rejected.
* Optional e-mails honor per-category preferences and carry a one-click unsubscribe (token
  encrypted with `APP_KEY`; the landing page needs a click, so link scanners don't unsubscribe
  people). Security e-mails are not optional. Marketing is opt-in, timestamped and audited.
* Rate limits: showing requests/bookings 10/min per user; unsubscribe and inquiries 5/min, 20/h.

## 3. Planned controls (by sprint)

| Sprint | Control |
|---|---|
| 4 | R2 storage for media (private bucket for documents), malware scanning for uploads |
| 9 | Document access policies, signed URLs (≤5 min), access logs, antivirus scanning (ClamAV) before availability, retention policies, `Idempotency-Key` on offers/transactions/appointments |
| 11 | Admin audit UI, alerting on suspicious security events |
| 12 | DAST (OWASP ZAP baseline) in CI, dependency/license review, backup restore drills, penetration test |

## 4. Real-estate compliance considerations (require specialist review)

* **Fair Housing Act / NJ Law Against Discrimination**: no steering; search and AI features must
  not use protected characteristics; Equal Housing Opportunity notice in the footer.
* **NJ Real Estate Commission advertising rules**: brokerage name/licence disclosures on listings and
  agent pages (to be confirmed with the broker of record).
* **Agent licences**: licence numbers are stored as *unverified* until an administrator verifies
  them against the NJ Real Estate Commission's official records.
* **MLS/IDX policies**: see `docs/mls/integration-plan.md`.
* **Privacy**: collect minimum PII; retention schedules; access/deletion requests; marketing consent
  (CAN-SPAM, TCPA for SMS).
* Generated documents are never presented as legal advice; no automatic contract acceptance.

## 5. Operational security

Secrets live in the hosting platform's secret store / GitHub Environments, never in the repo.
Production runs with `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=.<domain>`,
HTTPS everywhere (Cloudflare), database over TLS, least-privilege DB user, encrypted backups with
tested restores, and log retention that excludes secrets and tokens.
