<?php

namespace App\Notifications\Search;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\SavedSearch;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SavedSearchMatchesNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    /**
     * @param  list<array{slug: string, title: string, list_price: float|null, listing_type: string, city: string|null, state: string|null}>  $listings
     */
    public function __construct(
        public readonly SavedSearch $search,
        public readonly array $listings,
        public readonly int $total,
    ) {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return NotificationEvent::SavedSearchAlerts;
    }

    /**
     * Mail only reaches verified addresses; the in-app notification is always stored.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->preferredChannels($notifiable);

        return $notifiable->hasVerifiedEmail() ? $channels : array_values(array_diff($channels, ['mail']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = config('hitlease.frontend_url');
        $message = (new MailMessage)
            ->subject("New listings for \"{$this->search->name}\"")
            ->line("{$this->total} new listing(s) match your saved search \"{$this->search->name}\".");

        foreach ($this->listings as $listing) {
            $price = $listing['list_price'] !== null ? '$'.number_format($listing['list_price']).($listing['listing_type'] === 'rent' ? '/mo' : '') : 'Price on request';
            $message->line("• {$listing['title']} — {$price} — {$listing['city']}, {$listing['state']}: {$frontend}/properties/{$listing['slug']}");
        }

        return $this->withUnsubscribe($message
            ->action('Manage saved searches and alerts', "{$frontend}/dashboard/saved-searches")
            ->line('You receive this e-mail because you turned on alerts for this search. You can turn them off at any time from the link above.'), $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'saved_search.matches',
            'saved_search_id' => $this->search->id,
            'name' => $this->search->name,
            'total' => $this->total,
            'listings' => $this->listings,
        ];
    }
}
