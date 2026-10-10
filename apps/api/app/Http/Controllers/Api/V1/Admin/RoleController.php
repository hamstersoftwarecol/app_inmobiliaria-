<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Shared\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()->with('permissions:id,name')->withCount('users')->orderByDesc('level')->get(),
        );
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('group')->orderBy('name')->get(['name', 'group', 'description']);

        return response()->json(['data' => $permissions]);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role, AuditLogger $audit): RoleResource
    {
        if ($role->name === RoleName::SuperAdmin->value) {
            throw new DomainException('role_locked', 'The super admin role always has every permission.', 409);
        }

        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $ids = Permission::query()->whereIn('name', $request->validated('permissions'))->pluck('id');
        $role->permissions()->sync($ids);

        $role->load('permissions:id,name');
        $audit->log(AuditCategory::Admin, 'roles.permissions_updated', $request->user(), $role,
            ['permissions' => $before], ['permissions' => $role->permissions->pluck('name')->sort()->values()->all()]);

        return new RoleResource($role->loadCount('users'));
    }
}
