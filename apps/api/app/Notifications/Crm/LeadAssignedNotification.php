<?php

namespace App\Notifications\Crm;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\Lead;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Minimal PII by e-mail: the lead's name and a link; contact details stay in the app. */
class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    public function __construct(public readonly Lead $lead)
    {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return NotificationEvent::LeadUpdates;
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
        $url = config('app_inmobiliaria.frontend_url')."/dashboard/crm/leads/{$this->lead->id}";

        $message = (new MailMessage)
            ->subject('New lead assigned to you')
            ->line("{$this->lead->full_name} was assigned to you (source: {$this->lead->source->value}).")
            ->action('Open the lead', $url)
            ->line('Respond promptly: the first contact time is tracked in CRM reports.');

        return $this->withUnsubscribe($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'lead.assigned', 'lead_id' => $this->lead->id, 'name' => $this->lead->full_name, 'source' => $this->lead->source->value];
    }
}
