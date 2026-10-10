<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Permission as PermissionName;
use App\Domain\Identity\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent: syncs the permission catalogue and system roles from the
 * domain enums. Safe to run on every deployment:
 *   php artisan db:seed --class=RolesAndPermissionsSeeder --force
 *
 * Default role permissions are only applied when a role is first created,
 * so customizations made by super admins are preserved — except for the
 * super admin role, which always receives every permission.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (PermissionName::cases() as $permission) {
                Permission::query()->updateOrCreate(
                    ['name' => $permission->value],
                    ['group' => $permission->group()],
                );
            }

            Permission::query()->whereNotIn('name', PermissionName::values())->delete();

            foreach (RoleName::cases() as $roleName) {
                $role = Role::query()->firstOrNew(['name' => $roleName->value]);
                $isNew = ! $role->exists;

                $role->fill([
                    'display_name' => $roleName->displayName(),
                    'description' => $roleName->description(),
                    'level' => $roleName->level(),
                    'is_staff' => $roleName->isStaff(),
                    'is_system' => true,
                ])->save();

                if ($isNew || $roleName === RoleName::SuperAdmin) {
                    $role->permissions()->sync(
                        Permission::query()
                            ->whereIn('name', array_map(fn ($p) => $p->value, $roleName->defaultPermissions()))
                            ->pluck('id'),
                    );
                }
            }
        });
    }
}
