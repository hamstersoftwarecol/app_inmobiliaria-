<?php

use Illuminate\Support\Facades\Schedule;

// Agency time zone so "expired yesterday" matches New Jersey calendar days.
Schedule::command('hitlease:licenses:expire')
    ->dailyAt('00:15')
    ->timezone(config('hitlease.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// MLS: incremental syncs when due (per-provider refresh interval), nightly reconciliation.
Schedule::command('hitlease:mls:sync-due')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('hitlease:mls:sync-due --type=reconcile')
    ->dailyAt('03:30')
    ->timezone(config('hitlease.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Saved-search alerts (agency morning; weekly digests on Mondays).
Schedule::command('hitlease:saved-searches:alert --frequency=daily')
    ->dailyAt('07:45')
    ->timezone(config('hitlease.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('hitlease:saved-searches:alert --frequency=weekly')
    ->weeklyOn(1, '07:50')
    ->timezone(config('hitlease.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();
