<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1 201 ### ####'),
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password!2345'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function deactivated(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill([
            'status' => UserStatus::Deactivated,
            'deactivated_at' => now(),
        ])->save());
    }

    public function role(RoleName ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            $user->roles()->attach(Role::query()->whereIn('name', array_map(fn ($r) => $r->value, $roles))->pluck('id'));
            $user->flushPermissionCache();
        });
    }

    /**
     * Enables TOTP with the given secret (tests compute codes from it).
     */
    public function withMfa(string $secret): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => [hash('sha256', 'recov-ery01')],
        ])->save());
    }
}
