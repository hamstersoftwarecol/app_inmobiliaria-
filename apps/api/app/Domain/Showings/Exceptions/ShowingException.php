<?php

namespace App\Domain\Showings\Exceptions;

use App\Domain\Shared\DomainException;

final class ShowingException extends DomainException
{
    public static function slotUnavailable(): self
    {
        return new self('slot_unavailable', 'That time is no longer available. Please choose another slot.', 409);
    }

    public static function notSchedulable(): self
    {
        return new self('showings_unavailable', 'Showings cannot be scheduled for this listing online. Please contact the agent.', 422);
    }

    public static function invalidTransition(string $from, string $action): self
    {
        return new self('appointment_invalid_transition', "A {$from} showing cannot be {$action}.", 409, ['status' => $from]);
    }

    public static function inThePast(): self
    {
        return new self('appointment_in_past', 'Choose a time in the future.', 422);
    }

    public static function notStarted(): self
    {
        return new self('appointment_not_started', 'A showing can only be marked completed or no-show once it has started.', 409);
    }

    public static function idempotencyMismatch(): self
    {
        return new self('idempotency_key_reused', 'This Idempotency-Key was already used with a different request.', 422);
    }

    public static function idempotencyInProgress(): self
    {
        return new self('idempotency_in_progress', 'A request with this Idempotency-Key is still being processed.', 409);
    }
}
