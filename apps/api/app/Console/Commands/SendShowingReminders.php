<?php

namespace App\Console\Commands;

use App\Domain\Showings\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\Showings\ShowingReminderNotification;
use Illuminate\Console\Command;

class SendShowingReminders extends Command
{
    protected $signature = 'hitlease:showings:reminders';

    protected $description = 'Remind visitors and agents 24 hours and 1 hour before confirmed showings';

    public function handle(): int
    {
        $sent = $this->remind('1h', 'reminder_1h_sent_at', 60) + $this->remind('24h', 'reminder_24h_sent_at', 24 * 60);
        $this->info("Sent {$sent} showing reminder(s).");

        return self::SUCCESS;
    }

    private function remind(string $window, string $column, int $minutes): int
    {
        $sent = 0;
        Appointment::query()
            ->where('status', AppointmentStatus::Confirmed->value)
            ->whereNull($column)
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addMinutes($minutes))
            ->with(['requester', 'agent.user', 'property'])
            ->chunkById(200, function ($appointments) use ($window, $column, &$sent) {
                foreach ($appointments as $a) {
                    // Claim first so overlapping runs never double-send.
                    $update = [$column => now()];
                    if ($window === '1h') {
                        $update['reminder_24h_sent_at'] = $a->reminder_24h_sent_at ?? now(); // no stale "tomorrow" mail afterwards
                    }
                    if (Appointment::query()->whereKey($a->id)->whereNull($column)->update($update) !== 1) {
                        continue;
                    }
                    foreach (array_filter([$a->requester, $a->agent?->user]) as $user) {
                        $user->notify(new ShowingReminderNotification($a, $window));
                    }
                    $sent++;
                }
            });

        return $sent;
    }
}
