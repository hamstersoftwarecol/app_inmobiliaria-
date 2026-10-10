# ADR-0002: Sanctum cookie sessions for web, personal access tokens for mobile

* Status: Accepted · Date: 2026-10-10

## Context
Storing bearer tokens in browser storage exposes them to XSS. Mobile apps cannot use cookies
comfortably but have hardware-backed secure storage.

## Decision
* Web: Sanctum SPA authentication (HttpOnly session cookie + CSRF token). `POST /auth/login` refuses
  non-stateful requests.
* Mobile: `POST /auth/token` issues expiring, revocable personal access tokens stored with
  `flutter_secure_storage`.
* Both channels share `CredentialVerifier` (lockout, status checks) and the same MFA rules.

## Consequences
+ No tokens in the browser; per-device revocation for mobile.
− The web app and API must share a registrable domain (`SESSION_DOMAIN=.example.com`) and CORS must
  allow credentials for the exact web origin.
