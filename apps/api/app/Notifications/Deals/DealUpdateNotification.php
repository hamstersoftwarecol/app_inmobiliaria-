<?php

namespace App\Notifications\Deals;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Offer and transaction events for the agents (and clients) involved. Amounts are not put in the
 * e-mail; the recipient opens the app to see details.
 */
class DealUpdateNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly string $type, public readonly string $summary, public readonly string $path, public readonly array $data = [])
    {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return str_starts_with($this->type, 'offer.') ? NotificationEvent::OfferUpdates : NotificationEvent::TransactionUpdates;
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
        return $this->withUnsubscribe((new MailMessage)
            ->subject($this->summary)
            ->line($this->summary)
            ->action('Open in App Inmobiliaria', config('app_inmobiliaria.frontend_url').$this->path), $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => $this->type, 'summary' => $this->summary, 'path' => $this->path, ...$this->data];
    }
}
