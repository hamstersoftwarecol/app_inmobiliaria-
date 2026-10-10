<?php

namespace App\Domain\Deals\Exceptions;

use App\Domain\Shared\DomainException;

final class DealException extends DomainException
{
    public static function invalidTransition(string $from, string $action): self
    {
        return new self('offer_invalid_transition', "A {$from} offer cannot be {$action}.", 409, ['status' => $from]);
    }

    public static function propertyUnderContract(): self
    {
        return new self('property_under_contract', 'This property already has an accepted offer and an open transaction.', 409);
    }

    public static function transactionClosed(): self
    {
        return new self('transaction_not_open', 'Only open transactions can be changed.', 409);
    }

    public static function offerExpired(): self
    {
        return new self('offer_expired', 'This offer has expired.', 409);
    }

    public static function notOfferable(): self
    {
        return new self('property_not_offerable', 'Offers can only be recorded for listings that are not closed.', 422);
    }

    public static function noAgentProfile(): self
    {
        return new self('agent_profile_missing', 'Your account has no agent profile.', 422);
    }
}
