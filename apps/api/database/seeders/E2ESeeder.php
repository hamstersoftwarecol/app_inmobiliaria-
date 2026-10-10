<?php

namespace Database\Seeders;

use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Identity\Enums\RoleName;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
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
    }
}
