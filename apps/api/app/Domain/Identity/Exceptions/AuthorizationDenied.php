<?php

namespace App\Domain\Identity\Exceptions;

use App\Domain\Shared\DomainException;

final class AuthorizationDenied extends DomainException
{
    public static function roleEscalation(string $role): self
    {
        return new self('role_escalation_denied', "You are not allowed to assign the role [{$role}].", 403);
    }

    public static function cannotManageUser(): self
    {
        return new self('user_management_denied', 'You are not allowed to manage this user.', 403);
    }

    public static function cannotModifySelf(): self
    {
        return new self('self_modification_denied', 'You cannot perform this action on your own account.', 403);
    }

    public static function mfaEnrollmentRequired(): self
    {
        return new self('mfa_enrollment_required', 'Multi-factor authentication must be enabled to access this area.', 403);
    }
}
