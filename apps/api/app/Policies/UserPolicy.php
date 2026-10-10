<?php

namespace App\Policies;

use App\Application\Identity\UserAdministration;
use App\Domain\Identity\Enums\Permission;
use App\Models\User;

/**
 * Combines RBAC permissions with the role hierarchy so that, e.g., a broker
 * can manage agents but not other brokers or super admins.
 */
class UserPolicy
{
    public function __construct(private readonly UserAdministration $administration) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::UsersViewAny);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || $actor->hasPermission(Permission::UsersViewAny);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::UsersCreate);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::UsersUpdate) && $this->administration->canManage($actor, $target);
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::UsersAssignRoles) && $this->administration->canManage($actor, $target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::UsersDeactivate) && $this->administration->canManage($actor, $target);
    }
}
