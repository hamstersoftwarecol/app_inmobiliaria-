<?php

namespace App\Application\Notifications;

use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class NotificationPreferences
{
    public function wantsMail(User $user, NotificationEvent $event): bool
    {
        $stored = DB::table('notification_preferences')->where('user_id', $user->id)->where('event', $event->value)->value('mail');

        return $stored === null ? $event->defaultMail() : (bool) $stored;
    }

    /**
     * @return array<string, array{mail: bool, consented_at: string|null}>
     */
    public function all(User $user): array
    {
        $rows = DB::table('notification_preferences')->where('user_id', $user->id)->get()->keyBy('event');

        return collect(NotificationEvent::cases())->mapWithKeys(fn (NotificationEvent $e) => [$e->value => [
            'mail' => isset($rows[$e->value]) ? (bool) $rows[$e->value]->mail : $e->defaultMail(),
            'consented_at' => isset($rows[$e->value]->consented_at) ? (string) $rows[$e->value]->consented_at : null,
        ]])->all();
    }

    public function set(User $user, NotificationEvent $event, bool $mail): void
    {
        $existing = DB::table('notification_preferences')->where('user_id', $user->id)->where('event', $event->value)->first();
        DB::table('notification_preferences')->updateOrInsert(
            ['user_id' => $user->id, 'event' => $event->value],
            [
                'mail' => $mail,
                // Opt-in record (who agreed and when) for consent-based categories such as marketing.
                'consented_at' => $mail ? ($existing->consented_at ?? now()) : null,
                'updated_at' => now(),
            ],
        );
    }

    /** Opaque token for one-click unsubscribe links (encrypted with APP_KEY, no expiry). */
    public function unsubscribeToken(User $user, NotificationEvent $event): string
    {
        return Crypt::encryptString(json_encode(['u' => $user->id, 'e' => $event->value], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: User, 1: NotificationEvent}|null
     */
    public function resolveToken(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }
        $user = User::query()->find($data['u'] ?? null);
        $event = NotificationEvent::tryFrom((string) ($data['e'] ?? ''));

        return $user && $event ? [$user, $event] : null;
    }
}
