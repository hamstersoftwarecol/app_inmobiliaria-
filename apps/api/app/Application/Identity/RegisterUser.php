<?php

namespace App\Application\Identity;

use App\Application\Audit\AuditLogger;
use App\Domain\Identity\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Public self-registration. Only consumer roles (buyer/tenant, seller/owner)
 * can be chosen; staff and agents are created by administrators.
 */
class RegisterUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{first_name: string, last_name: string, email: string, password: string, phone?: string|null, locale?: string|null, account_type?: string|null}  $data
     */
    public function handle(array $data): User
    {
        $role = RoleName::tryFrom($data['account_type'] ?? RoleName::Buyer->value) ?? RoleName::Buyer;

        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'] ?? 'en',
                'password' => $data['password'],
            ]);

            $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

            return $user;
        });

        $this->audit->security('auth.registered', $user, ['role' => $role->value]);

        event(new Registered($user));

        return $user->load('roles');
    }
}
