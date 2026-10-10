<?php

namespace App\Http\Resources;

use App\Domain\Identity\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    private bool $withPermissions = false;

    public function withPermissions(): self
    {
        $this->withPermissions = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'status' => $this->status->value,
            'email_verified' => $this->email_verified_at !== null,
            'mfa_enabled' => $this->hasMfaEnabled(),
            'is_staff' => $this->whenLoaded('roles', fn () => $this->isStaff()),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn (Role $role) => [
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values()),
            'permissions' => $this->when($this->withPermissions, fn () => $this->isSuperAdmin()
                ? Permission::values()
                : $this->permissionNames()->all()),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
