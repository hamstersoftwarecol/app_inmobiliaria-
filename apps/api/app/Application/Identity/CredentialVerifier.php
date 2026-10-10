<?php

namespace App\Application\Identity;

use App\Application\Audit\AuditLogger;
use App\Domain\Identity\Exceptions\AuthenticationFailed;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Verifies e-mail/password pairs with brute-force protection (per e-mail + IP)
 * and account status checks. Shared by the cookie (web) and token (mobile)
 * login flows so both apply identical rules.
 */
class CredentialVerifier
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function verify(string $email, string $password, string $ip): User
    {
        $key = $this->throttleKey($email, $ip);
        $maxAttempts = (int) config('app_inmobiliaria.security.login_max_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->audit->security('auth.login.locked_out', metadata: ['email' => Str::lower($email)]);

            throw AuthenticationFailed::tooManyAttempts(RateLimiter::availableIn($key));
        }

        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        // Always hash-check (even without user) to keep timing uniform.
        $valid = Hash::check($password, $user?->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || ! $valid) {
            RateLimiter::hit($key, (int) config('app_inmobiliaria.security.login_decay_seconds', 900));
            $this->audit->security('auth.login.failed', $user, ['email' => Str::lower($email)]);

            throw AuthenticationFailed::invalidCredentials();
        }

        if (! $user->isActive()) {
            $this->audit->security('auth.login.blocked_deactivated', $user);

            throw AuthenticationFailed::accountDeactivated();
        }

        RateLimiter::clear($key);

        return $user;
    }

    public function recordSuccessfulLogin(User $user, string $ip, string $channel): void
    {
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();
        $this->audit->security('auth.login.succeeded', $user, ['channel' => $channel]);
    }

    /**
     * Second-factor brute-force guard, per account, shared by web and mobile flows.
     */
    public function assertMfaNotLocked(User $user): void
    {
        $key = 'mfa-attempts:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, (int) config('app_inmobiliaria.security.login_max_attempts', 5))) {
            throw AuthenticationFailed::tooManyAttempts(RateLimiter::availableIn($key));
        }
    }

    public function recordMfaFailure(User $user): void
    {
        RateLimiter::hit('mfa-attempts:'.$user->getKey(), (int) config('app_inmobiliaria.security.login_decay_seconds', 900));
        $this->audit->security('auth.mfa.failed', $user);
    }

    public function clearMfaFailures(User $user): void
    {
        RateLimiter::clear('mfa-attempts:'.$user->getKey());
    }

    private function throttleKey(string $email, string $ip): string
    {
        return 'login:'.sha1(Str::lower(trim($email)).'|'.$ip);
    }
}
