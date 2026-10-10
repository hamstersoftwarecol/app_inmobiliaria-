<?php

namespace App\Notifications\Crm;

use App\Models\CrmTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CrmTask $task)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $zone = $notifiable->timezone ?: config('hitlease.agency_timezone');
        $due = $this->task->due_at->copy()->setTimezone($zone)->format('M j, Y g:i A T');

        return (new MailMessage)
            ->subject("Reminder: {$this->task->title}")
            ->line("Your task \"{$this->task->title}\" is due {$due}.")
            ->action('Open my tasks', config('hitlease.frontend_url').'/dashboard/crm/tasks');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'crm.task_due', 'task_id' => $this->task->id, 'title' => $this->task->title, 'due_at' => $this->task->due_at->toIso8601String(), 'lead_id' => $this->task->lead_id];
    }
}
