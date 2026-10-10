<?php

use Illuminate\Support\Facades\DB;
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

// CRM: task reminders shortly before each due time.
Schedule::command('hitlease:crm:task-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Showings: reminders 24 h and 1 h before confirmed showings; expired idempotency keys.
Schedule::command('hitlease:showings:reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => DB::table('idempotency_keys')->where('expires_at', '<', now())->delete())
    ->name('idempotency-keys:prune')->hourly()->onOneServer();

// Deals: offers past their response deadline; milestone reminders each agency morning.
Schedule::command('hitlease:offers:expire')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('hitlease:transactions:milestone-reminders')
    ->dailyAt('07:40')
    ->timezone(config('hitlease.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();
// Document retention purge is NOT scheduled: run `hitlease:documents:purge` (dry run) and add
// --force only after counsel confirms the retention period.
