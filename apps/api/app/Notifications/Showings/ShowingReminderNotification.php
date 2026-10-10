<?php

namespace App\Notifications\Showings;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\Appointment;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowingReminderNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    public function __construct(public readonly Appointment $appointment, public readonly string $window)
    {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return NotificationEvent::ShowingReminders;
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
        $a = $this->appointment;
        $place = $a->property?->title ?? 'the property';
        $address = $a->property ? trim("{$a->property->street_address}, {$a->property->city}, {$a->property->state}", ' ,') : null;
        $message = (new MailMessage)
            ->subject(($this->window === '1h' ? 'Starting soon' : 'Tomorrow').": showing of {$place}")
            ->line("Reminder: showing of {$place} on ".ShowingUpdateNotification::localTime($a, $notifiable->timezone ?? null).'.');
        if ($address) {
            $message->line("Address: {$address}");
        }

        return $this->withUnsubscribe($message->action('View showing', config('hitlease.frontend_url').'/dashboard/showings'), $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'showing.reminder', 'appointment_id' => $this->appointment->id, 'window' => $this->window, 'starts_at' => $this->appointment->starts_at->toIso8601String()];
    }
}
