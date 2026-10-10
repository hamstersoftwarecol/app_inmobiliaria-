<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\Lead;
use App\Models\User;

/**
 * `*_any` covers every lead; `*_assigned` only leads assigned to the user's own agent profile.
 */
class LeadPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::LeadsViewAny) || $actor->hasPermission(Permission::LeadsViewAssigned);
    }

    public function view(User $actor, Lead $lead): bool
    {
        return $actor->hasPermission(Permission::LeadsViewAny)
            || ($actor->hasPermission(Permission::LeadsViewAssigned) && $lead->isAssignedTo($actor));
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::LeadsCreate);
    }

    public function update(User $actor, Lead $lead): bool
    {
        return $actor->hasPermission(Permission::LeadsUpdateAny)
            || ($actor->hasPermission(Permission::LeadsUpdateAssigned) && $lead->isAssignedTo($actor));
    }

    public function assign(User $actor, Lead $lead): bool
    {
        return $actor->hasPermission(Permission::LeadsAssign);
    }

    /** Merging touches two leads and removes one: managers only. */
    public function merge(User $actor, Lead $lead): bool
    {
        return $actor->hasPermission(Permission::LeadsUpdateAny);
    }

    public function delete(User $actor, Lead $lead): bool
    {
        return $actor->hasPermission(Permission::LeadsDelete);
    }
}
