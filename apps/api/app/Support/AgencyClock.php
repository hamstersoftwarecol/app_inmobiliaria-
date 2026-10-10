<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Calendar dates as seen by the agency (America/New_York by default).
 * The application clock runs in UTC, so "today" in UTC flips 4–5 hours
 * before the New Jersey calendar day; date-only business rules such as
 * licence expiry must use the agency's day instead.
 */
final class AgencyClock
{
    public static function today(): Carbon
    {
        return Carbon::now(config('hitlease.agency_timezone', 'America/New_York'))->startOfDay();
    }

    public static function todayString(): string
    {
        return self::today()->toDateString();
    }
}
