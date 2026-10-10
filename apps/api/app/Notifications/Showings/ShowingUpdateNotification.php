<?php

namespace App\Notifications\Showings;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\Appointment;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Showing lifecycle messages. `kind`: requested (to the agent), confirmed, declined, cancelled,
 * rescheduled (to the other party). Confirmations carry an .ics calendar invite.
 */
class ShowingUpdateNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    public function __construct(public readonly Appointment $appointment, public readonly string $kind)
    {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return NotificationEvent::ShowingUpdates;
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
        $when = self::localTime($a, $notifiable->timezone ?? null);
        $place = $a->property?->title ?? 'the property';
        $subject = match ($this->kind) {
            'requested' => "Showing request: {$place}",
            'confirmed' => "Showing confirmed: {$place}",
            'declined' => "Showing request declined: {$place}",
            'cancelled' => "Showing cancelled: {$place}",
            default => "Showing rescheduled: {$place}",
        };
        $message = (new MailMessage)->subject($subject)->line(match ($this->kind) {
            'requested' => "{$a->contact_name} asked to see {$place} on {$when}.",
            'confirmed' => "Your showing of {$place} is confirmed for {$when}.",
            'declined' => "The showing of {$place} on {$when} could not be accepted.",
            'cancelled' => "The showing of {$place} on {$when} was cancelled.",
            default => "The showing of {$place} is now on {$when}.",
        });
        if ($a->status_reason && in_array($this->kind, ['declined', 'cancelled'], true)) {
            $message->line('Reason: '.$a->status_reason);
        }
        $message->action('View showing', config('hitlease.frontend_url').'/dashboard/showings');
        if ($this->kind === 'confirmed') {
            $message->attachData(ShowingCalendar::ics($a), 'showing.ics', ['mime' => 'text/calendar; charset=utf-8; method=PUBLISH']);
        }

        return $this->withUnsubscribe($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => "showing.{$this->kind}",
            'appointment_id' => $this->appointment->id,
            'property_title' => $this->appointment->property?->title,
            'starts_at' => $this->appointment->starts_at->toIso8601String(),
        ];
    }

    public static function localTime(Appointment $a, ?string $zone): string
    {
        return $a->starts_at->copy()->setTimezone($zone ?: $a->timezone)->format('l, M j, Y \a\t g:i A T');
    }
}
