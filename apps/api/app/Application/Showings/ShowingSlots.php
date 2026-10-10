<?php

namespace App\Application\Showings;

use App\Domain\Showings\Enums\AppointmentStatus;
use App\Models\Agent;
use App\Models\Appointment;
use Illuminate\Support\Carbon;

/**
 * Bookable showing slots for an agent on one local calendar day.
 *
 * Weekly windows are local wall-clock times in the agency's zone; each date is converted on its
 * own, so slots stay at the same local times across DST changes (a window that falls in the
 * spring-forward gap starts at the first existing local time).
 */
final class ShowingSlots
{
    /**
     * @return list<array{starts_at: Carbon, ends_at: Carbon}> UTC instants, earliest first
     */
    public function forDate(Agent $agent, string $date, ?Carbon $now = null, ?int $ignoreAppointmentId = null): array
    {
        $now ??= now();
        $tz = $agent->timezone();
        $duration = (int) config('hitlease.showings.duration_minutes', 30);
        $step = max(5, (int) config('hitlease.showings.slot_step_minutes', 30));
        $earliest = $now->copy()->addHours((int) config('hitlease.showings.min_notice_hours', 2));
        $latestDay = $now->copy()->setTimezone($tz)->startOfDay()->addDays((int) config('hitlease.showings.max_days_ahead', 30));

        $day = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        if ($day->gt($latestDay) || $day->copy()->endOfDay()->lt($earliest)) {
            return [];
        }

        $candidates = [];
        foreach ($agent->availabilities()->where('weekday', $day->isoWeekday())->get() as $window) {
            $cursor = Carbon::parse("{$date} {$window->start_time}", $tz);
            $windowEnd = Carbon::parse("{$date} {$window->end_time}", $tz);
            while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                if ($cursor->format('Y-m-d') === $date && $cursor->gte($earliest)) {
                    $candidates[$cursor->getTimestamp()] = $cursor->copy();
                }
                $cursor->addMinutes($step);
            }
        }
        if ($candidates === []) {
            return [];
        }
        ksort($candidates);

        // Query bounds in UTC: the database stores instants, not New Jersey wall-clock times.
        $from = reset($candidates)->copy()->utc();
        $to = end($candidates)->copy()->addMinutes($duration)->utc();
        $busy = $agent->timeOff()->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get(['starts_at', 'ends_at'])
            ->concat(Appointment::query()
                ->where('agent_id', $agent->id)
                ->whereIn('status', [AppointmentStatus::Requested->value, AppointmentStatus::Confirmed->value])
                ->when($ignoreAppointmentId, fn ($q, $id) => $q->whereKeyNot($id))
                ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
                ->get(['starts_at', 'ends_at']));

        $slots = [];
        foreach ($candidates as $start) {
            $end = $start->copy()->addMinutes($duration);
            $clash = $busy->contains(fn ($b) => $b->starts_at->lt($end) && $b->ends_at->gt($start));
            if (! $clash) {
                $slots[] = ['starts_at' => $start->copy()->utc(), 'ends_at' => $end->utc()];
            }
        }

        return $slots;
    }

    public function isAvailable(Agent $agent, Carbon $startsAt, ?int $ignoreAppointmentId = null): bool
    {
        $date = $startsAt->copy()->setTimezone($agent->timezone())->format('Y-m-d');

        return collect($this->forDate($agent, $date, null, $ignoreAppointmentId))
            ->contains(fn (array $slot) => $slot['starts_at']->getTimestamp() === $startsAt->getTimestamp());
    }
}
