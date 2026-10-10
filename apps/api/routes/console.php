<?php

use App\Http\Controllers\Api\V1\System\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Agency time zone so "expired yesterday" matches New Jersey calendar days.
Schedule::command('app-inmobiliaria:licenses:expire')
    ->dailyAt('00:15')
    ->timezone(config('app_inmobiliaria.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// MLS: incremental syncs when due (per-provider refresh interval), nightly reconciliation.
Schedule::command('app-inmobiliaria:mls:sync-due')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('app-inmobiliaria:mls:sync-due --type=reconcile')
    ->dailyAt('03:30')
    ->timezone(config('app_inmobiliaria.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Saved-search alerts (agency morning; weekly digests on Mondays).
Schedule::command('app-inmobiliaria:saved-searches:alert --frequency=daily')
    ->dailyAt('07:45')
    ->timezone(config('app_inmobiliaria.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('app-inmobiliaria:saved-searches:alert --frequency=weekly')
    ->weeklyOn(1, '07:50')
    ->timezone(config('app_inmobiliaria.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// CRM: task reminders shortly before each due time.
Schedule::command('app-inmobiliaria:crm:task-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Showings: reminders 24 h and 1 h before confirmed showings; expired idempotency keys.
Schedule::command('app-inmobiliaria:showings:reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => DB::table('idempotency_keys')->where('expires_at', '<', now())->delete())
    ->name('idempotency-keys:prune')->hourly()->onOneServer();

// Deals: offers past their response deadline; milestone reminders each agency morning.
Schedule::command('app-inmobiliaria:offers:expire')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('app-inmobiliaria:transactions:milestone-reminders')
    ->dailyAt('07:40')
    ->timezone(config('app_inmobiliaria.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();
// Document retention purge is NOT scheduled: run `app-inmobiliaria:documents:purge` (dry run) and add
// --force only after counsel confirms the retention period.

// Heartbeat read by /health/ready: proves the scheduler is running.
Schedule::call(fn () => Cache::put(HealthController::HEARTBEAT_KEY, now()->getTimestamp(), 600))
    ->name('scheduler:heartbeat')->everyMinute()->onOneServer();
