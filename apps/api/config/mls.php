<?php

use App\Infrastructure\Mls\Providers\BrightMLSProvider;
use App\Infrastructure\Mls\Providers\FixtureMLSProvider;
use App\Infrastructure\Mls\Providers\GSMLSProvider;
use App\Infrastructure\Mls\Providers\NJMLSProvider;
use App\Infrastructure\Mls\Providers\RESOWebAPIProvider;

return [

    /*
    | Driver key => adapter class. New providers are added here without
    | touching the sync pipeline (Open/Closed principle).
    */
    'drivers' => [
        'reso_web_api' => RESOWebAPIProvider::class,
        'njmls' => NJMLSProvider::class,
        'gsmls' => GSMLSProvider::class,
        'bright' => BrightMLSProvider::class,
        // Development/testing only — refused in staging/production.
        'fixture' => FixtureMLSProvider::class,
    ],

    'fixture_path' => base_path('tests/Fixtures/mls'),

    'sync' => [
        // Upper bound of incremental batches per job run (the next run continues from the cursor).
        'max_batches_per_run' => (int) env('MLS_MAX_BATCHES_PER_RUN', 25),
        'import_chunk' => 100,
        // Reconciliation refuses to hide more than this share of active listings at once
        // (protects against a truncated/failed feed wiping the inventory).
        'reconcile_max_removal_ratio' => (float) env('MLS_RECONCILE_MAX_REMOVAL_RATIO', 0.2),
        'max_photos' => 50,
    ],

    'http' => [
        'timeout' => (int) env('MLS_HTTP_TIMEOUT', 30),
        'retries' => 3,
        'retry_base_ms' => 500,
        // Client-side ceiling per connection, under any provider quota.
        'max_requests_per_minute' => (int) env('MLS_MAX_REQUESTS_PER_MINUTE', 60),
    ],
];
