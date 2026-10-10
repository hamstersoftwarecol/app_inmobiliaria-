<?php

namespace Database\Seeders;

use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Identity\Enums\RoleName;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Office;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Deterministic fixtures for Playwright end-to-end tests. Refuses to run
 * outside local/testing. Credentials come from E2E_PASSWORD / E2E_MFA_SECRET.
 * All names, e-mails (@example.com) and licence numbers are fictitious.
 */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('E2ESeeder can only run in local/testing environments.');
        }

        $password = (string) env('E2E_PASSWORD', 'E2e-Password-123!');
        $mfaSecret = (string) env('E2E_MFA_SECRET', 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');

        $user = function (string $email, string $first, string $last, RoleName $role, bool $mfa = false) use ($password, $mfaSecret): User {
            $u = User::query()->updateOrCreate(['email' => $email], ['first_name' => $first, 'last_name' => $last, 'password' => $password]);
            $u->forceFill([
                'email_verified_at' => now(),
                'mfa_secret' => $mfa ? $mfaSecret : null,
                'mfa_enabled_at' => $mfa ? now() : null,
            ])->save();
            $u->roles()->sync(Role::query()->where('name', $role->value)->pluck('id'));

            return $u;
        };

        $user('e2e.broker@example.com', 'Erin', 'Broker', RoleName::Broker, mfa: true);
        $buyer = $user('e2e.buyer@example.com', 'Bianca', 'Buyer', RoleName::Buyer);
        $buyer->favoriteProperties()->detach();
        $buyer->savedSearches()->delete();
        // Leads and tasks created by earlier runs (contact form + CRM flows).
        Lead::withTrashed()->where('email_key', 'like', 'e2e.lead.%@example.com')->forceDelete();
        CrmTask::query()->where('title', 'like', 'E2E %')->delete();
        // Users created through the admin UI by earlier runs.
        User::withTrashed()->where('email', 'like', 'e2e.created.%@example.com')->forceDelete();

        $agency = Agency::query()->updateOrCreate(['slug' => 'e2e-realty'], ['name' => 'E2E Test Realty', 'license_number' => 'TEST-BROKERAGE']);
        $office = Office::query()->updateOrCreate(['agency_id' => $agency->id, 'slug' => 'e2e-office'], [
            'name' => 'E2E Office', 'street_address' => '100 Example Avenue', 'city' => 'Hoboken', 'postal_code' => '07030',
        ]);

        $agent = function (string $email, string $first, string $last, string $slug, string $number, LicenseStatus $status) use ($user, $agency, $office): void {
            $u = $user($email, $first, $last, RoleName::Agent);
            $a = Agent::query()->withTrashed()->updateOrCreate(['user_id' => $u->id], [
                'agency_id' => $agency->id, 'office_id' => $office->id, 'slug' => $slug, 'status' => 'active',
                'is_public' => true, 'languages' => ['en', 'es'], 'specialties' => ['first_time_buyers'],
                'bio' => 'Fictitious agent used by automated tests.', 'deleted_at' => null,
            ]);
            $a->licenses()->delete();
            AgentLicense::query()->forceCreate([
                'agent_id' => $a->id, 'license_number' => $number, 'license_state' => 'NJ', 'license_type' => 'salesperson',
                'status' => $status, 'expires_on' => now()->addYear()->toDateString(),
                'verified_at' => $status === LicenseStatus::Verified ? now() : null,
                'verification_source' => $status === LicenseStatus::Verified ? 'document_review' : null,
                'verification_reference' => $status === LicenseStatus::Verified ? 'E2E fixture' : null,
            ]);
        };

        $agent('e2e.verified@example.com', 'Valeria', 'Verified', 'e2e-valeria-verified', 'TESTE2E0001', LicenseStatus::Verified);
        $agent('e2e.pending@example.com', 'Pablo', 'Pending', 'e2e-pablo-pending', 'TESTE2E0002', LicenseStatus::PendingVerification);

        // Listings: one published, one draft ready for a broker to publish.
        $valeria = Agent::query()->where('slug', 'e2e-valeria-verified')->firstOrFail();
        $type = PropertyType::query()->where('code', 'single_family')->value('id');
        foreach ([
            ['slug' => 'e2e-12-example-lane-hoboken', 'title' => 'E2E published colonial', 'published' => true, 'street' => '12 Example Lane', 'lat' => 40.7440, 'lng' => -74.0324],
            ['slug' => 'e2e-draft-34-sample-street', 'title' => 'E2E draft townhouse', 'published' => false, 'street' => '34 Sample Street', 'lat' => 40.7452, 'lng' => -74.0301],
        ] as $row) {
            Property::query()->withTrashed()->updateOrCreate(['slug' => $row['slug']], [
                'source' => 'internal', 'title' => $row['title'], 'listing_type' => 'sale', 'status' => 'active',
                'property_type_id' => $type, 'list_price' => 650000, 'street_address' => $row['street'], 'city' => 'Hoboken',
                'county' => 'Hudson', 'state' => 'NJ', 'postal_code' => '07030', 'bedrooms' => 3, 'bathrooms' => 2,
                'latitude' => $row['lat'], 'longitude' => $row['lng'], 'address_display' => true,
                'living_area_sqft' => 1800, 'description' => 'Fictitious listing used by automated tests.',
                'listing_agent_id' => $valeria->id, 'agency_id' => $agency->id,
                'is_published' => $row['published'], 'published_at' => $row['published'] ? now() : null, 'deleted_at' => null,
            ]);
            $property = Property::query()->where('slug', $row['slug'])->firstOrFail();
            // Reset what earlier runs added so every run starts from the same state.
            $property->media()->get()->each(function (PropertyMedia $media) {
                if ($media->disk && $media->path) {
                    Storage::disk($media->disk)->delete($media->path);
                }
                $media->delete();
            });
            $property->statusHistory()->delete();
        }
    }
}
