<?php

namespace App\Notifications\Crm;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Minimal PII by e-mail: the lead's name and a link; contact details stay in the app. */
class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead)
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
        $url = config('hitlease.frontend_url')."/dashboard/crm/leads/{$this->lead->id}";

        return (new MailMessage)
            ->subject('New lead assigned to you')
            ->line("{$this->lead->full_name} was assigned to you (source: {$this->lead->source->value}).")
            ->action('Open the lead', $url)
            ->line('Respond promptly: the first contact time is tracked in CRM reports.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'lead.assigned', 'lead_id' => $this->lead->id, 'name' => $this->lead->full_name, 'source' => $this->lead->source->value];
    }
}
