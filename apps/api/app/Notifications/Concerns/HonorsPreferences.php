<?php

namespace App\Notifications\Concerns;

use App\Application\Notifications\NotificationPreferences;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * Optional e-mails: sent only when the user keeps that category on (in-app is always stored), and
 * every message carries a one-click unsubscribe link and RFC 8058 List-Unsubscribe headers.
 */
trait HonorsPreferences
{
    abstract protected function preferenceEvent(): NotificationEvent;

    /**
     * @return list<string>
     */
    protected function preferredChannels(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }
        $channels = ['database'];
        if ($notifiable instanceof User && app(NotificationPreferences::class)->wantsMail($notifiable, $this->preferenceEvent())) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    protected function withUnsubscribe(MailMessage $message, object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            return $message;
        }
        $token = app(NotificationPreferences::class)->unsubscribeToken($notifiable, $this->preferenceEvent());
        $page = config('app_inmobiliaria.frontend_url').'/unsubscribe?token='.urlencode($token);
        $oneClick = url('/api/v1/notifications/unsubscribe').'?token='.urlencode($token);

        return $message
            ->line("Don't want these e-mails? Unsubscribe: {$page}")
            ->withSymfonyMessage(function (Email $email) use ($oneClick) {
                $email->getHeaders()->addTextHeader('List-Unsubscribe', "<{$oneClick}>");
                $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
