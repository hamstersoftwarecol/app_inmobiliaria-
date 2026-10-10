<?php

namespace App\Domain\Agency\Enums;

/**
 * Lifecycle of an agent's real estate licence record:
 *   pending_verification ─► verified ─► expired | revoked
 *            └────────────► rejected
 * A typed licence number is never trusted until a verifier checks it
 * against an official source and records how.
 */
enum LicenseStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::PendingVerification => [self::Verified, self::Rejected],
            self::Verified => [self::Expired, self::Revoked],
            self::Rejected, self::Expired, self::Revoked => [],
        }, true);
    }

    /** Statuses that reserve the licence number (only one active record per number). */
    public function isActive(): bool
    {
        return in_array($this, [self::PendingVerification, self::Verified], true);
    }
}
