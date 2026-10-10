<?php

namespace App\Notifications\Agency;

use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\AgentLicense;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseStatusChangedNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    public function __construct(public readonly AgentLicense $license)
    {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return NotificationEvent::LicenceUpdates;
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
        $license = $this->license;
        $label = "{$license->license_state} {$license->license_type->label()} licence {$license->license_number}";

        $message = (new MailMessage)->subject('Your licence verification status changed');

        $message = match ($license->status) {
            LicenseStatus::Verified => $message->line("Your {$label} has been verified.")
                ->line('Your public agent profile can now be listed if you opted in.'),
            LicenseStatus::Rejected => $message->line("Your {$label} could not be verified.")
                ->line('Reason: '.($license->status_reason ?? 'not provided'))
                ->line('Please review the details and submit it again.'),
            LicenseStatus::Revoked => $message->line("The verification of your {$label} was revoked.")
                ->line('Reason: '.($license->status_reason ?? 'not provided')),
            LicenseStatus::Expired => $message->line("Your {$label} has expired.")
                ->line('Submit the renewed licence so it can be verified again.'),
            default => $message->line("Your {$label} status is now {$license->status->value}."),
        };

        return $this->withUnsubscribe($message, $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'license.status_changed',
            'license_id' => $this->license->id,
            'status' => $this->license->status->value,
            'reason' => $this->license->status_reason,
        ];
    }
}
