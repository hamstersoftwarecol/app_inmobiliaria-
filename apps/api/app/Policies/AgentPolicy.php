<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::AgentsViewAny) || $actor->hasPermission(Permission::AgentsManage);
    }

    public function view(User $actor, Agent $agent): bool
    {
        return $actor->id === $agent->user_id || $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::AgentsManage);
    }

    public function update(User $actor, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::AgentsManage);
    }

    public function updateOwn(User $actor, Agent $agent): bool
    {
        return $actor->id === $agent->user_id && $actor->hasPermission(Permission::AgentsManageOwnProfile);
    }

    public function submitLicense(User $actor, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::AgentsManage) || $this->updateOwn($actor, $agent);
    }
}
