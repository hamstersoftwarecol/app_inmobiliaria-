<?php

namespace App\Console\Commands;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\CrmTask;
use App\Notifications\Crm\TaskReminderNotification;
use Illuminate\Console\Command;

class SendCrmTaskReminders extends Command
{
    protected $signature = 'hitlease:crm:task-reminders {--lead-minutes=15 : Remind this many minutes before the due time}';

    protected $description = 'Remind assignees about CRM tasks that are due (each task once per due time)';

    public function handle(): int
    {
        $sent = 0;
        CrmTask::query()
            ->whereNull('completed_at')
            ->whereNull('reminder_sent_at')
            ->where('due_at', '<=', now()->addMinutes((int) $this->option('lead-minutes')))
            ->whereHas('assignee', fn ($q) => $q->where('status', UserStatus::Active->value))
            ->with('assignee')
            ->chunkById(200, function ($tasks) use (&$sent) {
                foreach ($tasks as $task) {
                    // Claim first so overlapping runs cannot double-send.
                    $claimed = CrmTask::query()->whereKey($task->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                    if ($claimed === 1) {
                        $task->assignee->notify(new TaskReminderNotification($task));
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
