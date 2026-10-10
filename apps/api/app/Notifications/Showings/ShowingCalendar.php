<?php

namespace App\Notifications\Showings;

use App\Models\Appointment;

/** Minimal RFC 5545 event for calendar apps (times in UTC). */
final class ShowingCalendar
{
    public static function ics(Appointment $a): string
    {
        $fmt = fn ($t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $esc = fn (?string $v) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], (string) $v);
        $p = $a->property;
        $location = $p ? trim("{$p->street_address}, {$p->city}, {$p->state} {$p->postal_code}", ' ,') : '';

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Hitlease Real Estate//Showings//EN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:showing-'.$a->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($a->starts_at),
            'DTEND:'.$fmt($a->ends_at),
            'SUMMARY:'.$esc('Showing: '.($p?->title ?? 'property')),
            // A confirmed showing needs the address even when it is hidden on the public listing.
            'LOCATION:'.$esc($location),
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }
}
