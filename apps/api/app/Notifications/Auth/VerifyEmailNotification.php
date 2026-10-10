<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * E-mail verification. The link opens the web app, which forwards the signed
 * parameters to GET /api/v1/auth/email/verify/{id}/{hash}.
 */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function verificationUrl(object $notifiable): string
    {
        $signed = URL::temporarySignedRoute(
            'auth.verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        $parts = parse_url($signed);
        parse_str($parts['query'] ?? '', $query);
        $segments = explode('/', trim($parts['path'] ?? '', '/'));
        $hash = array_pop($segments);
        $id = array_pop($segments);

        return config('hitlease.frontend_url').'/verify-email?'.http_build_query(
            ['id' => $id, 'hash' => $hash] + $query,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your Hitlease Real Estate e-mail address')
            ->line('Please confirm your e-mail address to activate all account features.')
            ->action('Verify e-mail address', $this->verificationUrl($notifiable))
            ->line('If you did not create an account, no further action is required.');
    }
}
