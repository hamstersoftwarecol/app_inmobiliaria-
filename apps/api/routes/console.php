<?php

use Illuminate\Support\Facades\Schedule;

// Agency time zone so "expired yesterday" matches New Jersey calendar days.
Schedule::command('hitlease:licenses:expire')
    ->dailyAt('00:15')
    ->timezone(config('hitlease.agency_timezone'))
    ->withoutOverlapping()
    ->onOneServer();
