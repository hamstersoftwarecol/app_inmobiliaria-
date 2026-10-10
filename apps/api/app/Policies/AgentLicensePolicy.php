<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\AgentLicense;
use App\Models\User;

/**
 * Self-verification is additionally blocked in LicenseVerificationService
 * (also for super admins, who bypass policies).
 */
class AgentLicensePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::AgentsVerifyLicenses) || $actor->hasPermission(Permission::AgentsViewAny);
    }

    public function review(User $actor, AgentLicense $license): bool
    {
        return $actor->hasPermission(Permission::AgentsVerifyLicenses);
    }
}
