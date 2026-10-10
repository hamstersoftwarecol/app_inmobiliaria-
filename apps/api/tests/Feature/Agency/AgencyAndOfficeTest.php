<?php

namespace Tests\Feature\Agency;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgencyAndOfficeTest extends TestCase
{
    private function officePayload(Agency $agency, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $agency->id,
            'name' => 'Hoboken Office',
            'street_address' => '1 Example Plaza',
            'city' => 'Hoboken',
            'county' => 'Hudson',
            'postal_code' => '07030',
            'latitude' => 40.7440,
            'longitude' => -74.0324,
        ], $overrides);
    }

    public function test_broker_manages_agency_and_offices(): void
    {
        $this->actingAsRole(RoleName::Broker);

        $agencyId = $this->postJson('/api/v1/admin/agencies', [
            'name' => 'Hitlease Real Estate',
            'license_number' => 'TEST-0001',
            'website' => 'https://www.example.com',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'hitlease-real-estate')
            ->json('data.id');
        $agency = Agency::findOrFail($agencyId);

        $this->patchJson("/api/v1/admin/agencies/{$agencyId}", ['phone' => '+1 201 555 0199'])->assertOk()
            ->assertJsonPath('data.phone', '+1 201 555 0199');

        $officeId = $this->postJson('/api/v1/admin/offices', $this->officePayload($agency, ['is_headquarters' => true]))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'hoboken-office')
            ->assertJsonPath('data.is_headquarters', true)
            ->json('data.id');

        // The geography column is derived by PostGIS from lat/lng.
        $distanceKm = DB::selectOne(
            'select ST_Distance(location, ST_SetSRID(ST_MakePoint(-74.0060, 40.7128), 4326)::geography) / 1000 as km from offices where id = ?',
            [$officeId],
        )->km;
        $this->assertEqualsWithDelta(3.7, $distanceKm, 0.5);

        // A new headquarters demotes the previous one.
        $this->postJson('/api/v1/admin/offices', $this->officePayload($agency, ['name' => 'Paramus', 'is_headquarters' => true]))->assertCreated();
        $this->assertFalse(Office::find($officeId)->is_headquarters);

        $this->getJson("/api/v1/admin/agencies/{$agencyId}")->assertOk()->assertJsonCount(2, 'data.offices');
        $this->assertTrue(AuditLog::where('event', 'offices.created')->exists());
    }

    public function test_office_validation(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $agency = Agency::factory()->create();

        $this->postJson('/api/v1/admin/offices', $this->officePayload($agency, ['postal_code' => 'ABCDE', 'latitude' => 95]))
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['postal_code', 'latitude']]]]);
    }

    public function test_office_with_active_agents_cannot_be_deleted(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $office = Office::factory()->create();
        Agent::factory()->create(['agency_id' => $office->agency_id, 'office_id' => $office->id]);

        $this->deleteJson("/api/v1/admin/offices/{$office->id}")->assertStatus(409)->assertJsonPath('error.code', 'office_has_agents');

        $empty = Office::factory()->create(['agency_id' => $office->agency_id]);
        $this->deleteJson("/api/v1/admin/offices/{$empty->id}")->assertNoContent();
        $this->assertSoftDeleted($empty);
    }

    public function test_office_manager_can_read_but_not_change_agency(): void
    {
        $this->actingAsRole(RoleName::OfficeManager);
        $agency = Agency::factory()->create();

        $this->getJson('/api/v1/admin/agencies')->assertOk();
        $this->getJson('/api/v1/admin/offices')->assertOk();
        $this->patchJson("/api/v1/admin/agencies/{$agency->id}", ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/v1/admin/offices', $this->officePayload($agency))->assertForbidden();
    }

    public function test_agents_and_buyers_cannot_use_agency_admin(): void
    {
        foreach ([RoleName::Agent, RoleName::Buyer] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/v1/admin/agencies')->assertForbidden();
        }
    }
}
