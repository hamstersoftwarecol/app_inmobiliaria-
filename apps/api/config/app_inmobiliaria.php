<?php

return [

    /*
    | Public web front-end URL. Used to build password-reset and e-mail
    | verification links that land on the Next.js application.
    */
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    /*
    | Default business time zone of the agency (New Jersey). Data is stored in
    | UTC and converted at the edges.
    */
    'agency_timezone' => env('AGENCY_TIMEZONE', 'America/New_York'),

    'security' => [
        // Staff roles must enrol in TOTP MFA before using administrative routes.
        'enforce_staff_mfa' => (bool) env('SECURITY_ENFORCE_STAFF_MFA', true),
        // Shared secret the Next.js server sends with SSR requests so rate limits apply per visitor
        // (X-App-Inmobiliaria-Client-IP) instead of to the web server's single address. Empty = disabled.
        'ssr_key' => env('API_SSR_KEY', ''),
        'staff_roles' => ['super_admin', 'broker', 'office_manager'],
        'login_max_attempts' => (int) env('SECURITY_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('SECURITY_LOGIN_DECAY_SECONDS', 900),
        // Per-IP ceiling for credential endpoints (login, register, reset...).
        'auth_requests_per_minute' => (int) env('SECURITY_AUTH_REQUESTS_PER_MINUTE', 10),
        // Seconds a password-verified login may wait for its MFA code.
        'mfa_challenge_ttl' => 300,
        'mfa_recovery_codes' => 8,
        // Roles a visitor may pick when self-registering.
        'self_registration_roles' => ['buyer', 'seller'],
    ],

    'showings' => [
        'duration_minutes' => (int) env('SHOWING_DURATION_MINUTES', 30),
        'slot_step_minutes' => (int) env('SHOWING_SLOT_STEP_MINUTES', 30),
        'min_notice_hours' => (int) env('SHOWING_MIN_NOTICE_HOURS', 2),
        'max_days_ahead' => (int) env('SHOWING_MAX_DAYS_AHEAD', 30),
    ],

    'health' => [
        // Readiness reports "degraded" above this many pending jobs across all queues.
        'max_queue_backlog' => (int) env('HEALTH_MAX_QUEUE_BACKLOG', 1000),
    ],

    'documents' => [
        // Private disk (never web-served). Cloudflare R2 replaces it once the bucket exists.
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_kilobytes' => (int) env('DOCUMENTS_MAX_KB', 25600),
        'link_minutes' => (int) env('DOCUMENTS_LINK_MINUTES', 5),
        // Years kept after closing/cancellation (confirm the period with counsel).
        'retention_years' => (int) env('DOCUMENTS_RETENTION_YEARS', 6),
        // none | clamd. With clamd, files are downloadable only after a clean scan.
        'scanner' => env('DOCUMENT_SCANNER', 'none'),
        'clamd_host' => env('CLAMD_HOST', '127.0.0.1'),
        'clamd_port' => (int) env('CLAMD_PORT', 3310),
        'clamd_timeout' => (int) env('CLAMD_TIMEOUT', 30),
    ],

    'media' => [
        // Filesystem disk for uploaded listing photos ("public" locally, an
        // S3-compatible disk such as Cloudflare R2 in production).
        'disk' => env('MEDIA_DISK', 'public'),
        'max_photos' => 50,
        'max_kilobytes' => 15360,
    ],

    'mls' => [
        // Global kill switch. Production syncs stay off until licences and
        // official provider specifications are in place.
        'sync_enabled' => (bool) env('MLS_SYNC_ENABLED', false),
        'http_timeout' => (int) env('MLS_HTTP_TIMEOUT', 30),
        'default_page_size' => 200,
    ],
];
