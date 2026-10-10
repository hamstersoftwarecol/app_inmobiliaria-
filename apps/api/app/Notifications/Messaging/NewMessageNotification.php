<?php

namespace App\Notifications\Messaging;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\Message;
use App\Notifications\Concerns\HonorsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * New chat message. E-mail never contains the message text (it may be sensitive) and is sent at
 * most once per conversation per hour per recipient; in-app notices are always stored.
 */
class NewMessageNotification extends Notification implements ShouldQueue
{
    use HonorsPreferences, Queueable;

    public function __construct(public readonly Message $message, public readonly bool $mail)
    {
        $this->onQueue('notifications');
    }

    protected function preferenceEvent(): NotificationEvent
    {
        return NotificationEvent::Messages;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->preferredChannels($notifiable);

        return $this->mail ? $channels : array_values(array_diff($channels, ['mail']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $from = $this->message->author?->full_name ?? 'Someone';

        return $this->withUnsubscribe((new MailMessage)
            ->subject("New message from {$from}")
            ->line("{$from} sent you a message on Hitlease.")
            ->action('Read the message', config('hitlease.frontend_url').'/dashboard/messages/'.$this->message->conversation_id), $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'message.new',
            'conversation_id' => $this->message->conversation_id,
            'from' => $this->message->author?->full_name,
        ];
    }
}
