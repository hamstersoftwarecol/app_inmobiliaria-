<?php

namespace App\Domain\Identity\Exceptions;

use App\Domain\Shared\DomainException;

final class AuthenticationFailed extends DomainException
{
    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', 'These credentials do not match our records.', 422);
    }

    public static function accountDeactivated(): self
    {
        return new self('account_deactivated', 'This account has been deactivated. Contact the agency for assistance.', 403);
    }

    public static function tooManyAttempts(int $seconds): self
    {
        return new self('too_many_attempts', "Too many login attempts. Try again in {$seconds} seconds.", 429, ['retry_after' => $seconds]);
    }

    public static function mfaRequired(): self
    {
        return new self('mfa_required', 'A multi-factor authentication code is required.', 422);
    }

    public static function invalidMfaCode(): self
    {
        return new self('invalid_mfa_code', 'The authentication code is invalid or has already been used.', 422);
    }

    public static function mfaChallengeExpired(): self
    {
        return new self('mfa_challenge_expired', 'The login attempt expired. Please sign in again.', 422);
    }
}
