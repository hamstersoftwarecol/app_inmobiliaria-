<?php

namespace Tests\Feature\Listings;

use App\Application\Mls\MLSSyncDispatcher;
use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
{
    use MlsTestHelpers;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Renovated colonial near the park',
            'listing_type' => 'sale',
            'property_type_id' => PropertyType::where('code', 'single_family')->value('id'),
            'list_price' => 725000,
            'street_address' => '12 Example Lane',
            'city' => 'Montclair',
            'county' => 'Essex',
            'postal_code' => '07042',
            'bedrooms' => 4,
            'bathrooms' => 2.5,
            'amenities' => ['garage', 'fireplace'],
        ], $overrides);
    }

    private function actingAsAgentProfile(?Agent $agent = null): Agent
    {
        $agent ??= Agent::factory()->create();
        Sanctum::actingAs(User::find($agent->user_id)->fresh());

        return $agent;
    }

    public function test_agent_creates_own_listing_but_cannot_publish(): void
    {
        $agent = $this->actingAsAgentProfile();

        $id = $this->postJson('/api/v1/manage/properties', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.listing_agent.id', $agent->id)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.publication_missing', [])
            ->assertJsonPath('data.slug', '12-example-lane-montclair-nj')
            ->json('data.id');

        // Agents cannot list under another agent.
        $other = Agent::factory()->create();
        $this->postJson('/api/v1/manage/properties', $this->payload(['listing_agent_id' => $other->id]))
            ->assertStatus(422)->assertJsonPath('error.code', 'listing_agent_invalid');

        $this->postJson("/api/v1/manage/properties/{$id}/publish")->assertForbidden();
        $this->assertSame('Montclair', Property::find($id)->city);
        $this->assertTrue(AuditLog::where('event', 'properties.created')->exists());
    }

    public function test_agents_cannot_touch_other_agents_listings(): void
    {
        $owner = Agent::factory()->create();
        $property = Property::factory()->forAgent($owner)->create();
        $this->actingAsAgentProfile();

        $this->getJson("/api/v1/manage/properties/{$property->id}")->assertForbidden();
        $this->patchJson("/api/v1/manage/properties/{$property->id}", ['title' => 'Hijacked listing'])->assertForbidden();
        $this->getJson('/api/v1/manage/properties')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsAgentProfile($owner);
        $this->getJson('/api/v1/manage/properties')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/manage/properties/{$property->id}", ['title' => 'Updated by owner'])->assertOk();
    }

    public function test_consumers_cannot_manage_listings(): void
    {
        $this->actingAsRole(RoleName::Buyer);
        $this->getJson('/api/v1/manage/properties')->assertForbidden();
        $this->postJson('/api/v1/manage/properties', $this->payload())->assertForbidden();
    }

    public function test_broker_publishes_after_requirements_are_met_and_history_is_kept(): void
    {
        $agent = Agent::factory()->create();
        $property = Property::factory()->forAgent($agent)->create(['postal_code' => null]);
        $this->actingAsRole(RoleName::Broker);

        $this->postJson("/api/v1/manage/properties/{$property->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'listing_not_publishable')
            ->assertJsonPath('error.details.missing', ['postal_code']);

        $this->patchJson("/api/v1/manage/properties/{$property->id}", ['postal_code' => '07030'])->assertOk();
        $this->postJson("/api/v1/manage/properties/{$property->id}/publish")->assertOk()->assertJsonPath('data.is_published', true);

        $this->patchJson("/api/v1/manage/properties/{$property->id}", ['list_price' => 499000, 'status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.status_history.0.from_price', 550000)
            ->assertJsonPath('data.status_history.0.to_price', 499000)
            ->assertJsonPath('data.status_history.0.to_status', 'pending');

        // Removing a required field takes the listing down automatically.
        $this->patchJson("/api/v1/manage/properties/{$property->id}", ['street_address' => null])->assertOk()->assertJsonPath('data.is_published', false);

        $this->deleteJson("/api/v1/manage/properties/{$property->id}")->assertNoContent();
        $this->assertSoftDeleted($property);
    }

    public function test_office_manager_can_view_but_not_edit(): void
    {
        $property = Property::factory()->create();
        $this->actingAsRole(RoleName::OfficeManager);

        $this->getJson("/api/v1/manage/properties/{$property->id}")->assertOk();
        $this->patchJson("/api/v1/manage/properties/{$property->id}", ['title' => 'Edited by manager'])->assertForbidden();
    }

    public function test_validation(): void
    {
        $this->actingAsAgentProfile();

        $this->postJson('/api/v1/manage/properties', $this->payload([
            'list_price' => -5, 'postal_code' => 'ABC', 'bathrooms' => 2.3, 'amenities' => ['helipad'], 'status' => 'delete',
        ]))->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['fields' => ['list_price', 'postal_code', 'bathrooms', 'amenities.0', 'status']]]]);
    }

    public function test_mls_listings_are_read_only_for_staff(): void
    {
        $connection = $this->authorizedConnection();
        app(MLSSyncDispatcher::class)->dispatch($connection, 'incremental');
        $mls = Property::where('source', 'mls')->firstOrFail();
        $this->actingAsRole(RoleName::Broker);

        $this->patchJson("/api/v1/manage/properties/{$mls->id}", ['title' => 'Changed MLS data'])
            ->assertStatus(409)->assertJsonPath('error.code', 'mls_listing_read_only');
        $this->postJson("/api/v1/manage/properties/{$mls->id}/unpublish")
            ->assertStatus(409)->assertJsonPath('error.code', 'mls_publication_managed');
        $this->deleteJson("/api/v1/manage/properties/{$mls->id}")->assertStatus(409);

        $this->getJson("/api/v1/manage/properties/{$mls->id}")->assertOk()->assertJsonPath('data.source', 'mls')
            ->assertJsonPath('data.mls.listing_key', $mls->mlsListing->listing_key);
    }
}
