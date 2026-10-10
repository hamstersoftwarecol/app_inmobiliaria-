<?php

namespace App\Domain\Mls\Enums;

/**
 * Internal listing status, aligned with RESO StandardStatus.
 */
enum ListingStatus: string
{
    case Active = 'active';
    case ActiveUnderContract = 'active_under_contract';
    case ComingSoon = 'coming_soon';
    case Pending = 'pending';
    case Closed = 'closed';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case Canceled = 'canceled';
    case Hold = 'hold';
    case Delete = 'delete';
    case Incomplete = 'incomplete';
    case Unknown = 'unknown';

    public static function fromReso(?string $value): self
    {
        $normalized = strtolower(preg_replace('/[^a-z]/i', '', (string) $value) ?? '');

        return match ($normalized) {
            'active' => self::Active,
            'activeundercontract' => self::ActiveUnderContract,
            'comingsoon' => self::ComingSoon,
            'pending' => self::Pending,
            'closed' => self::Closed,
            'withdrawn' => self::Withdrawn,
            'expired' => self::Expired,
            'canceled', 'cancelled' => self::Canceled,
            'hold' => self::Hold,
            'delete' => self::Delete,
            'incomplete' => self::Incomplete,
            default => self::Unknown,
        };
    }

    /**
     * Statuses that must be removed from public display.
     */
    public function isOffMarket(): bool
    {
        return in_array($this, [self::Withdrawn, self::Expired, self::Canceled, self::Delete, self::Hold, self::Incomplete], true);
    }
}
