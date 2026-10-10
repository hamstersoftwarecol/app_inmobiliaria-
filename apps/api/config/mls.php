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

    'http' => [
        'timeout' => (int) env('MLS_HTTP_TIMEOUT', 30),
        'retries' => 3,
        'retry_base_ms' => 500,
        // Client-side ceiling per connection, under any provider quota.
        'max_requests_per_minute' => (int) env('MLS_MAX_REQUESTS_PER_MINUTE', 60),
    ],
];
