<?php

namespace App\Domain\Crm\Exceptions;

use App\Domain\Shared\DomainException;

final class CrmException extends DomainException
{
    public static function agentNotAssignable(): self
    {
        return new self('lead_agent_invalid', 'Leads can only be assigned to active agents.', 422);
    }

    public static function mergeInvalid(string $reason): self
    {
        return new self('lead_merge_invalid', $reason, 422);
    }

    public static function noAgentProfile(): self
    {
        return new self('agent_profile_missing', 'Your account has no agent profile, so leads cannot be assigned to you.', 422);
    }

    public static function rangeTooLarge(): self
    {
        return new self('range_too_large', 'Choose a period of at most one year.', 422);
    }
}
