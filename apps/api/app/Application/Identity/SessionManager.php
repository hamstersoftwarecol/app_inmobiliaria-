<?php

namespace App\Application\Identity;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lists and revokes a user's active authentication sessions: browser
 * sessions (database session driver) and mobile API tokens (Sanctum).
 */
class SessionManager
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(User $user, ?string $currentSessionId, ?int $currentTokenId): Collection
    {
        $web = collect();
        if (config('session.driver') === 'database') {
            $web = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn ($row) => [
                    'id' => 'web:'.hash('sha256', $row->id),
                    'type' => 'web',
                    'name' => 'Browser session',
                    'ip_address' => $row->ip_address,
                    'user_agent' => $row->user_agent,
                    'last_used_at' => Carbon::createFromTimestamp($row->last_activity)->toIso8601String(),
                    'expires_at' => null,
                    'is_current' => $currentSessionId !== null && hash_equals($row->id, $currentSessionId),
                ]);
        }

        $tokens = $user->tokens()->orderByDesc('last_used_at')->get()->map(fn ($token) => [
            'id' => 'token:'.$token->id,
            'type' => 'token',
            'name' => $token->name,
            'ip_address' => null,
            'user_agent' => null,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'is_current' => $currentTokenId === $token->id,
        ]);

        return $web->concat($tokens)->values();
    }

    /**
     * Revokes one session by its public id ("web:<sha256>" or "token:<id>").
     */
    public function revoke(User $user, string $publicId): bool
    {
        [$type, $value] = array_pad(explode(':', $publicId, 2), 2, '');

        if ($type === 'token' && ctype_digit($value)) {
            return $user->tokens()->whereKey((int) $value)->delete() > 0;
        }

        if ($type === 'web' && config('session.driver') === 'database') {
            $ids = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->pluck('id')
                ->filter(fn (string $id) => hash_equals(hash('sha256', $id), $value));

            return $ids->isNotEmpty()
                && DB::table(config('session.table', 'sessions'))->whereIn('id', $ids)->delete() > 0;
        }

        return false;
    }

    public function revokeAll(User $user, ?string $exceptSessionId = null, ?int $exceptTokenId = null): void
    {
        $user->tokens()->when($exceptTokenId, fn ($q) => $q->whereKeyNot($exceptTokenId))->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
                ->delete();
        }

        // Invalidate "remember me" cookies on other devices.
        $user->forceFill(['remember_token' => null])->saveQuietly();
    }
}
