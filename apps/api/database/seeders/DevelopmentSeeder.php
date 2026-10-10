<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Mls\Enums\ProviderStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\MlsProvider;
use App\Models\Office;
use App\Models\Property;
use App\Models\PropertyType;
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

        // Demo internal listings (fictitious addresses) for local UI work.
        $demoAgent = Agent::query()->whereHas('user', fn ($q) => $q->where('email', 'agent@hitlease.test'))->first();
        foreach ([
            ['slug' => 'demo-100-example-avenue-hackensack', 'title' => 'Demo: renovated colonial', 'type' => 'single_family', 'price' => 689000, 'street' => '100 Example Avenue', 'city' => 'Hackensack', 'zip' => '07601', 'lat' => 40.8859, 'lng' => -74.0435, 'listing' => 'sale'],
            ['slug' => 'demo-200-sample-street-jersey-city', 'title' => 'Demo: waterfront condo for rent', 'type' => 'condominium', 'price' => 3200, 'street' => '200 Sample Street', 'city' => 'Jersey City', 'zip' => '07302', 'lat' => 40.7178, 'lng' => -74.0431, 'listing' => 'rent'],
        ] as $row) {
            Property::query()->updateOrCreate(['slug' => $row['slug']], [
                'source' => 'internal', 'title' => $row['title'], 'listing_type' => $row['listing'], 'status' => 'active',
                'property_type_id' => PropertyType::query()->where('code', $row['type'])->value('id'),
                'list_price' => $row['price'], 'street_address' => $row['street'], 'city' => $row['city'], 'state' => 'NJ',
                'postal_code' => $row['zip'], 'latitude' => $row['lat'], 'longitude' => $row['lng'], 'bedrooms' => 3, 'bathrooms' => 2,
                'description' => 'Demo listing for local development only.', 'listing_agent_id' => $demoAgent?->id,
                'agency_id' => $agency->id, 'is_published' => true, 'published_at' => now(),
            ]);
        }

        $this->command?->warn("Development accounts (<role>@hitlease.test) password: {$password}");
    }
}
