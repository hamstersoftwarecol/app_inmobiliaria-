<?php

namespace App\Domain\Agency\Exceptions;

use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Shared\DomainException;

final class AgencyException extends DomainException
{
    public static function invalidLicenseTransition(LicenseStatus $from, LicenseStatus $to): self
    {
        return new self('license_transition_invalid', "A licence cannot move from [{$from->value}] to [{$to->value}].", 409);
    }

    public static function selfVerification(): self
    {
        return new self('license_self_verification', 'You cannot verify, reject or revoke your own licence.', 403);
    }

    public static function duplicateLicense(): self
    {
        return new self('license_duplicate', 'This licence number is already pending or verified for an agent.', 409);
    }

    public static function licenseAlreadyExpired(): self
    {
        return new self('license_already_expired', 'A licence with a past expiration date cannot be verified.', 422);
    }

    public static function agentProfileExists(): self
    {
        return new self('agent_profile_exists', 'This user already has an agent profile.', 409);
    }

    public static function userNotEligible(): self
    {
        return new self('agent_user_not_eligible', 'Only active users with the agent or broker role can have an agent profile.', 422);
    }

    public static function officeOutsideAgency(): self
    {
        return new self('office_outside_agency', 'The office does not belong to the agent\'s agency.', 422);
    }

    public static function officeHasAgents(): self
    {
        return new self('office_has_agents', 'Reassign the office\'s active agents before deleting it.', 409);
    }

    public static function agentProfileMissing(): self
    {
        return new self('agent_profile_missing', 'Your account does not have an agent profile yet. Ask your broker to create it.', 404);
    }
}
