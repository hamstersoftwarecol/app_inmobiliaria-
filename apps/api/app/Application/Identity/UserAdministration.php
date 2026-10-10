<?php

namespace App\Application\Identity;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Exceptions\AuthorizationDenied;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Administrative user operations with privilege-escalation guards:
 * an actor can only assign roles strictly below their highest level and can
 * only manage users whose highest level is strictly below theirs.
 * Super admins are exempt from the hierarchy (but still audited).
 */
class UserAdministration
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionManager $sessions,
    ) {}

    public function canManage(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return $actor->isSuperAdmin() || $target->highestRoleLevel() < $actor->highestRoleLevel();
    }

    /**
     * @param  list<string>  $roleNames
     * @return Collection<int, Role>
     */
    public function assertAssignableRoles(User $actor, array $roleNames): Collection
    {
        $roles = Role::query()->whereIn('name', $roleNames)->get();

        foreach ($roles as $role) {
            if (! $actor->isSuperAdmin() && $role->level >= $actor->highestRoleLevel()) {
                throw AuthorizationDenied::roleEscalation($role->name);
            }
        }

        return $roles;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roleNames
     */
    public function create(User $actor, array $attributes, array $roleNames): User
    {
        $roles = $this->assertAssignableRoles($actor, $roleNames);

        $user = DB::transaction(function () use ($actor, $attributes, $roles) {
            $user = User::create($attributes);
            $user->roles()->attach($roles->pluck('id')->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $actor->getKey()]])->all());

            return $user;
        });

        $this->audit->log(AuditCategory::Admin, 'users.created', $actor, $user, new: [
            'email' => $user->email,
            'roles' => $roles->pluck('name')->all(),
        ]);

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, User $target, array $attributes): User
    {
        $this->assertCanManage($actor, $target);

        $target->fill($attributes);
        $changes = $target->getDirty();
        $original = array_intersect_key($target->getOriginal(), $changes);

        if (array_key_exists('email', $changes)) {
            $target->email_verified_at = null;
        }
        $target->save();

        if ($changes !== []) {
            $this->audit->log(AuditCategory::Admin, 'users.updated', $actor, $target, $original, $changes);
        }

        return $target->load('roles');
    }

    /**
     * @param  list<string>  $roleNames
     */
    public function syncRoles(User $actor, User $target, array $roleNames): User
    {
        $this->assertCanManage($actor, $target);
        $roles = $this->assertAssignableRoles($actor, $roleNames);

        $before = $target->roles->pluck('name')->all();
        $target->roles()->sync($roles->pluck('id')->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $actor->getKey()]])->all());
        $target->flushPermissionCache();

        $this->audit->log(AuditCategory::Admin, 'users.roles_synced', $actor, $target, ['roles' => $before], ['roles' => $roles->pluck('name')->all()]);

        return $target->load('roles');
    }

    public function deactivate(User $actor, User $target): User
    {
        $this->assertCanManage($actor, $target);

        $target->forceFill(['status' => UserStatus::Deactivated, 'deactivated_at' => now()])->save();
        $this->sessions->revokeAll($target);

        $this->audit->log(AuditCategory::Admin, 'users.deactivated', $actor, $target);

        return $target->load('roles');
    }

    public function reactivate(User $actor, User $target): User
    {
        $this->assertCanManage($actor, $target);

        $target->forceFill(['status' => UserStatus::Active, 'deactivated_at' => null])->save();
        $this->audit->log(AuditCategory::Admin, 'users.reactivated', $actor, $target);

        return $target->load('roles');
    }

    private function assertCanManage(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw AuthorizationDenied::cannotModifySelf();
        }

        if (! $this->canManage($actor, $target)) {
            throw AuthorizationDenied::cannotManageUser();
        }
    }
}
