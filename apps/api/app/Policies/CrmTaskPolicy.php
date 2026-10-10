<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\CrmTask;
use App\Models\User;

/** Tasks belong to their assignee; lead managers (`leads.assign`) can see and manage everyone's. */
class CrmTaskPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::LeadsViewAny) || $actor->hasPermission(Permission::LeadsViewAssigned);
    }

    public function manage(User $actor, CrmTask $task): bool
    {
        return $task->assigned_to === $actor->id
            || $task->created_by === $actor->id
            || $actor->hasPermission(Permission::LeadsAssign);
    }
}
