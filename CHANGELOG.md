# Changelog

All notable changes. Versions follow the API contract (`docs/api/openapi.yaml`).

## Unreleased

* **Mobile (Flutter):** new screens for offers (list, negotiation, record, counter, accept, reject,
  withdraw), the agent directory and agent profiles, account settings (edit profile, change
  password, two-factor setup and recovery codes, signed-in devices, e-mail preferences, account
  closure) and agent tools (availability and time off, new lead, own agent profile and licence
  submission). No API changes.

## 1.0.0-rc.1 — 2026-10-10

Release candidate covering Sprints 1–12.

* **Identity & security:** cookie (web) and token (mobile) auth, TOTP MFA with recovery codes,
  sessions, brute-force protection, RBAC (6 roles, 50 permissions), audit log, security headers,
  idempotency keys, CSV-injection-safe audited exports.
* **Agency:** agencies, offices, agent profiles, licence verification workflow, public directory.
* **Listings & MLS:** listing management with photos and publication rules; MLS engine (RESO Web
  API adapter, normalization, display/compliance rules, sync monitor) — real MLS connections await
  licences and credentials.
* **Search:** filters, radius/bbox/polygon, sorting, autocomplete, favorites, saved searches with
  alerts.
* **CRM:** inquiries, leads pipeline, timeline, tasks with reminders, duplicates/merge, reports.
* **Showings & notifications:** availability, DST-safe slots, no double-booking, reminders,
  notification center, e-mail preferences, one-click unsubscribe.
* **Deals:** offers and counter-offers, transactions with NJ milestones, private versioned documents
  with signed downloads and optional ClamAV scanning, private messaging.
* **Mobile (Flutter):** search, listing detail, favorites, saved searches, showings, notifications,
  messages and an agent mode.
* **Reports:** agency KPI dashboard with activity charts; audited CSV exports.
* **Operations:** readiness probe with scheduler heartbeat, JSON logs, k6 load-test script, ZAP
  baseline workflow, axe accessibility checks, backup/restore scripts with a recorded drill,
  runbooks.

**Not included / blocked** (see `docs/product-backlog.md`): real MLS feeds, maps (Mapbox), push
notifications (Firebase), Cloudflare R2, deployed ClamAV, production mail and hosting, e-signature,
store release, capacity test and penetration test, legal review of copy and retention periods.
