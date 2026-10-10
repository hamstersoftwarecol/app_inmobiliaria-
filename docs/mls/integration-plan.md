# MLS Integration Plan

> **Status: blocked by external authorization.** No MLS data licence, IDX/VOW agreement,
> credentials or official technical specification has been provided yet. Nothing in this
> repository contacts a real MLS. Production synchronization stays disabled until the items in
> §6 are complete.

## 1. Goals

Display listings from authorized New Jersey–area MLSs (NJMLS, Garden State MLS, Bright MLS and
other RESO-compatible providers) next to the agency's own listings, with correct attribution,
freshness and per-provider display rules.

## 2. What is implemented (Sprint 1)

| Piece | Location | Notes |
|---|---|---|
| Provider contract | `app/Domain/Mls/Contracts/MLSProviderInterface.php` | `code`, `capabilities`, `authenticate`, `fetchMetadata`, `fetchListings`, `fetchListing`, `fetchMedia`, `fetchChanges` (returns `MLSChangeBatch` with an opaque cursor). Adapted from the proposed interface: capabilities + metadata were added because providers differ. |
| DTOs | `app/Domain/Mls/DTOs` | `MLSListingDTO` keeps compliance fields (source MLS, ListingKey, ListingId, originating system, broker, attribution, modification timestamp, display flags) plus the untouched raw payload. Composite key `source:listingKey`. |
| Generic RESO Web API adapter | `app/Infrastructure/Mls/Providers/RESOWebAPIProvider.php` | OData v4 per RESO Web API Core: `Property`, `Media`, `$metadata`, `$filter/$orderby/$top`, `@odata.nextLink` paging (same-host only), OAuth2 client-credentials or static bearer token, HTTPS only, retries with exponential backoff on 429/5xx, client-side request budget, token cache, one forced refresh on 401. |
| Provider placeholders | `NJMLSProvider`, `GSMLSProvider`, `BrightMLSProvider` | Extend the RESO adapter with `SPECIFICATION_VERIFIED = false`. **Not** claimed to work against those MLSs: protocol, auth and field names must be confirmed from their official documentation. |
| Fixture provider | `FixtureMLSProvider` + `tests/Fixtures/mls/*.json` | Synthetic RESO-shaped data for development/tests; refuses to run outside `local`/`testing`. |
| Normalization | `app/Application/Mls/MLSNormalizationService.php` | RESO Data Dictionary → internal fields; StandardStatus mapping; address assembly; bathrooms (decimal/integer/full+half); m²→sqft; acres→sqft; HOA fee → monthly; invalid coordinates/years dropped; provider field-map overrides; media requires HTTPS and honours `Permission`. |
| Compliance | `app/Application/Mls/MLSComplianceService.php` | Deny-by-default: no rules ⇒ not displayed. Channel checks (web vs mobile), off-market hiding, AI-processing flag, photo limits, allowed fields, attribution templates. |
| Licence gate | `app/Infrastructure/Mls/MLSConnectionManager.php` | Production connections require `MLS_SYNC_ENABLED=true` **and** provider `status=authorized` + `is_enabled=true` (set by a super admin with explicit licence confirmation, audited). Sandbox connections can be built for handshake tests. |
| Admin API | `/api/v1/admin/mls/*` | Provider catalogue, display rules, encrypted connections (values never returned or logged), handshake test. |
| Tables | `mls_providers`, `mls_connections`, `mls_display_rules` | See `docs/database/erd.md`. |

## 3. Sync pipeline (implemented in Sprint 3; runs only against fixture/sandbox data until §6 is complete)

```
Scheduler (per provider min_refresh_minutes)
  └─ SyncMLSListings job (queue mls-sync, unique per connection, rate-limited)
       ├─ provider.fetchChanges(cursor) ──► MLSChangeBatch
       ├─ MLSDeduplicationService (provider_id + listing_key, content hash)
       ├─ upsert mls_listings (raw payload, compliance fields) in a transaction
       ├─ materialize/update properties (only if MLSComplianceService allows display)
       ├─ status/price history, withdrawn/off-market ⇒ hide immediately
       ├─ dispatch SyncMLSMedia (queue mls-media) for changed media
       └─ persist cursor + mls_sync_logs ONLY after the batch commits
ImportMLSListings (initial load, resumable), ReconcileMLSListings (nightly key diff ⇒ detect removals)
```

Implementation: `MLSSyncRunner` (incremental / import / reconcile), `MLSListingSyncService`
(per-record transaction, content-hash skip, out-of-order protection by `ModificationTimestamp`),
`ListingMaterializer` (creates/updates `properties`, never publishes from a sandbox connection),
`MLSDeduplicationService`, `MLSMediaService`; jobs in `app/Jobs/Mls` are unique per connection and
release themselves on provider rate limits. The scheduler queues due incremental syncs every 5
minutes (`app-inmobiliaria:mls:sync-due`) and a nightly reconcile at 03:30 America/New_York. Reconcile
aborts (`mls_reconcile_aborted`) instead of hiding more than `MLS_RECONCILE_MAX_REMOVAL_RATIO`
(default 20 %) of a provider's listings in one run. Public pages re-check display rules at query
time (provider enabled, public/mobile flags, removal) and are rendered without caching.

Failure rules: a failed batch never deletes or overwrites valid data (transaction + cursor
advanced only on commit); retries with backoff; poison records are logged and skipped; the
connection is flagged `error` with the last message for the monitoring panel.

## 4. Data-use rules per provider (configurable in `mls_display_rules`)

Attribution template · mandatory disclaimer · minimum refresh interval · allowed fields ·
photo rules (max photos, watermark, hot-linking vs. copying to R2) · retention days ·
deletion behaviour · public display · mobile display · AI processing (default **off** — MLS data is
not sent to AI providers or used for training without contractual authorization).

## 5. Risks

| Risk | Mitigation |
|---|---|
| Provider is not RESO Web API (e.g. legacy RETS or proprietary) | Adapter per provider behind the same contract; RESO adapter is only the default. |
| Licence terms restrict mobile apps, photo storage or AI use | Flags default to deny; enforced server-side in `MLSComplianceService`. |
| Duplicate listings across MLSs | Composite keys + dedup service (address/geo + ListingId heuristics, human review for merges). |
| Rate limits / outages | Client budget, 429 `Retry-After`, exponential backoff, resumable cursors. |
| Stale or withdrawn listings shown | Off-market statuses hidden immediately; reconciliation job; freshness alerts. |
| Credential leakage | Encrypted at rest (APP_KEY), hidden from serialization, redacted in audit logs, never shipped to clients. |
| Scraping temptation | Explicitly prohibited: only licensed feeds; no scraping of Realtor.com, Zillow or other portals. |

## 6. Checklist before enabling production sync (owner: Broker + Super Admin)

1. Signed data licence / IDX (or VOW) agreement with each MLS, including mobile-app and photo terms.
2. Official API documentation, base URLs, auth method and sandbox credentials.
3. Field mapping reviewed against the provider's Data Dictionary implementation; set
   `SPECIFICATION_VERIFIED = true` only after contract tests against the sandbox pass.
4. Display rules (attribution, disclaimer, refresh, retention, photos) entered from the agreement.
5. Legal review of IDX display pages (Fair Housing, NJ advertising rules).
6. Monitoring/alerts configured; `MLS_SYNC_ENABLED=true` in production; provider authorized in the
   admin API with the licence reference (audited).
