<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Mls\Enums\ProviderStatus;
use App\Models\MlsProvider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local development data ONLY. Refuses to run in staging/production.
 * Creates one account per role (@hitlease.test, a reserved TLD) and the MLS
 * provider catalogue in "pending_authorization" state.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentSeeder can only run in local/testing environments.');
        }

        $password = env('DEV_SEED_PASSWORD') ?: Str::password(16);

        foreach (RoleName::cases() as $role) {
            $user = User::query()->firstOrCreate(
                ['email' => str_replace('_', '.', $role->value).'@hitlease.test'],
                [
                    'first_name' => 'Demo',
                    'last_name' => $role->displayName(),
                    'password' => $password,
                ],
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->roles()->syncWithoutDetaching(Role::query()->where('name', $role->value)->pluck('id'));
        }

        foreach ([
            ['code' => 'njmls', 'name' => 'New Jersey MLS (NJMLS)', 'driver' => 'njmls'],
            ['code' => 'gsmls', 'name' => 'Garden State MLS (GSMLS)', 'driver' => 'gsmls'],
            ['code' => 'bright', 'name' => 'Bright MLS', 'driver' => 'bright'],
        ] as $provider) {
            $model = MlsProvider::query()->firstOrCreate(['code' => $provider['code']], $provider + [
                'protocol' => 'reso_web_api',
                'notes' => 'Pending signed data licence and official specification.',
            ]);
            $model->forceFill(['status' => ProviderStatus::PendingAuthorization, 'is_enabled' => false])->save();
            $model->displayRule()->firstOrCreate([]);
        }

        $this->command?->warn("Development accounts (<role>@hitlease.test) password: {$password}");
    }
}
