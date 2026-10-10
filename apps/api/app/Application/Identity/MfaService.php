<?php

namespace App\Application\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) multi-factor authentication with single-use recovery codes.
 * Secrets are encrypted at rest through the User model casts; recovery codes
 * are stored as SHA-256 hashes so they cannot be recovered from the database.
 */
class MfaService
{
    private const ISSUER = 'App Inmobiliaria Real Estate';

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(self::ISSUER, $user->email, $secret);
    }

    /**
     * Verifies a TOTP code against a secret, rejecting replays of a code that
     * was already accepted for this user.
     */
    public function verifyCode(User $user, string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $cacheKey = "mfa:last_timestamp:{$user->getKey()}";
        $lastTimestamp = Cache::get($cacheKey);

        $timestamp = $this->google2fa->verifyKeyNewer($secret, $code, $lastTimestamp, 1);
        if ($timestamp === false) {
            return false;
        }

        $accepted = $timestamp === true ? $this->google2fa->getTimestamp() : $timestamp;
        Cache::put($cacheKey, $accepted, now()->addMinutes(5));

        return true;
    }

    /**
     * Verifies either a TOTP code or a recovery code (consumed on success).
     */
    public function verifyChallenge(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if (! $user->hasMfaEnabled()) {
            return false;
        }

        if ($code !== null && $code !== '') {
            return $this->verifyCode($user, (string) $user->mfa_secret, $code);
        }

        if ($recoveryCode !== null && $recoveryCode !== '') {
            return $this->consumeRecoveryCode($user, $recoveryCode);
        }

        return false;
    }

    /**
     * @return list<string> plain recovery codes, shown to the user only once
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $plain = [];
        for ($i = 0; $i < (int) config('app_inmobiliaria.security.mfa_recovery_codes', 8); $i++) {
            $plain[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        $user->forceFill([
            'mfa_recovery_codes' => array_map(fn (string $c) => hash('sha256', $c), $plain),
        ])->save();

        return $plain;
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $hash = hash('sha256', Str::lower(trim($code)));
        $codes = $user->mfa_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
        ])->save();
    }
}
