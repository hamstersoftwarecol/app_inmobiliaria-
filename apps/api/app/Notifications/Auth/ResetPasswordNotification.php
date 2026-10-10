<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset e-mail pointing to the Next.js reset page.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
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

    public function resetUrl(object $notifiable): string
    {
        return config('app_inmobiliaria.frontend_url').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Reset your App Inmobiliaria Real Estate password')
            ->line('We received a request to reset the password for your account.')
            ->action('Reset password', $this->resetUrl($notifiable))
            ->line("This link expires in {$minutes} minutes.")
            ->line('If you did not request a password reset, you can ignore this e-mail.');
    }
}
