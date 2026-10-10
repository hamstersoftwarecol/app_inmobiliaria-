<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Mls\Enums\ProviderStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\MlsProvider;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\UniqueSlug;
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

        // Demo agency/office and agent profiles; licences start pending so the
        // verification workflow can be exercised locally.
        $agency = Agency::query()->firstOrCreate(['slug' => 'hitlease-demo'], [
            'name' => 'Hitlease Real Estate (demo)',
            'timezone' => 'America/New_York',
        ]);
        $office = Office::query()->firstOrCreate(['agency_id' => $agency->id, 'slug' => 'demo-office'], [
            'name' => 'Demo Office',
            'street_address' => '100 Example Avenue',
            'city' => 'Hackensack',
            'county' => 'Bergen',
            'postal_code' => '07601',
            'is_headquarters' => true,
        ]);
        foreach (['agent@hitlease.test', 'broker@hitlease.test'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            if (! $user->agentProfile()->exists()) {
                $agent = new Agent(['office_id' => $office->id, 'languages' => ['en', 'es'], 'is_public' => true]);
                $agent->user()->associate($user);
                $agent->agency()->associate($agency);
                $agent->slug = UniqueSlug::for($user->full_name, Agent::query());
                $agent->save();
            }
        }

        $this->command?->warn("Development accounts (<role>@hitlease.test) password: {$password}");
    }
}
