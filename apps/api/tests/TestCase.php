<?php

namespace Tests;

use App\Domain\Identity\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    public const WEB_ORIGIN = 'http://localhost:3000';

    public const STRONG_PASSWORD = 'C0rrect-Horse-Battery!';

    public const MFA_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach real external services (MLS, HIBP, ...).
        Http::preventStrayRequests();
    }

    protected function userWithRole(RoleName $role, array $attributes = []): User
    {
        $factory = User::factory()->role($role);

        // Staff must be MFA-enrolled to reach admin routes.
        if ($role->isStaff()) {
            $factory = $factory->withMfa(self::MFA_SECRET);
        }

        return $factory->create($attributes)->fresh();
    }

    protected function actingAsRole(RoleName $role, array $attributes = []): User
    {
        $user = $this->userWithRole($role, $attributes);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function totp(string $secret = self::MFA_SECRET): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    /**
     * Headers that make Sanctum treat the request as coming from the first-party SPA.
     *
     * @return array<string, string>
     */
    protected function spaHeaders(): array
    {
        return ['Origin' => self::WEB_ORIGIN, 'Referer' => self::WEB_ORIGIN.'/login', 'Accept' => 'application/json'];
    }
}
