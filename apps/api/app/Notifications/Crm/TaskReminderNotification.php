<?php

namespace App\Notifications\Crm;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\CrmTask;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    public function __construct(public readonly CrmTask $task)
    {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return NotificationEvent::TaskReminders;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $zone = $notifiable->timezone ?: config('hitlease.agency_timezone');
        $due = $this->task->due_at->copy()->setTimezone($zone)->format('M j, Y g:i A T');

        $message = (new MailMessage)
            ->subject("Reminder: {$this->task->title}")
            ->line("Your task \"{$this->task->title}\" is due {$due}.")
            ->action('Open my tasks', config('hitlease.frontend_url').'/dashboard/crm/tasks');

        return $this->withUnsubscribe($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'crm.task_due', 'task_id' => $this->task->id, 'title' => $this->task->title, 'due_at' => $this->task->due_at->toIso8601String(), 'lead_id' => $this->task->lead_id];
    }
}
