<?php

namespace Tests\Feature\Listings;

use App\Application\Mls\MLSSyncDispatcher;
use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\MlsDisplayRule;
use App\Models\Property;
use Tests\TestCase;

class PublicPropertyComplianceTest extends TestCase
{
    use MlsTestHelpers;

    private function syncFixtures(array $rules = []): void
    {
        app(MLSSyncDispatcher::class)->dispatch($this->authorizedConnection(rules: $rules), 'incremental');
    }

    public function test_only_published_listings_are_public(): void
    {
        $published = Property::factory()->published()->create();
        $draft = Property::factory()->create();

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $published->slug);
        $this->getJson("/api/v1/properties/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/v1/properties/{$published->slug}")->assertOk()->assertJsonPath('data.compliance', null);
    }

    public function test_mls_listings_carry_attribution_disclaimer_and_freshness(): void
    {
        $this->syncFixtures();
        $slug = Property::whereHas('mlsListing', fn ($q) => $q->where('listing_key', 'TEST-KEY-0001'))->value('slug');

        $data = $this->getJson("/api/v1/properties/{$slug}")->assertOk()->json('data');

        $this->assertSame('Listing courtesy of Test Realty Office', $data['compliance']['attribution']);
        $this->assertSame('Synthetic test disclaimer.', $data['compliance']['disclaimer']);
        $this->assertSame('TEST-0001', $data['compliance']['listing_id']);
        $this->assertNotNull($data['compliance']['last_updated_at']);
        $this->assertCount(1, $data['photos'], 'private photo excluded');
        $this->assertSame('100 Example Avenue', $data['address']['street_address']);
    }

    public function test_allowed_fields_photo_limits_and_mobile_rules(): void
    {
        $this->syncFixtures(['allowed_fields' => ['bedrooms', 'bathrooms'], 'allow_mobile_display' => false, 'photo_rules' => ['max_photos' => 0]]);
        $slug = Property::whereHas('mlsListing', fn ($q) => $q->where('listing_key', 'TEST-KEY-0001'))->value('slug');

        $data = $this->getJson("/api/v1/properties/{$slug}")->assertOk()->json('data');
        $this->assertSame(3, $data['details']['bedrooms']);
        $this->assertNull($data['details']['year_built']);
        $this->assertNull($data['details']['annual_taxes']);
        $this->assertSame([], $data['photos']);

        $this->getJson("/api/v1/properties/{$slug}", ['X-App-Inmobiliaria-Client' => 'mobile'])->assertNotFound();
        $this->getJson('/api/v1/properties', ['X-App-Inmobiliaria-Client' => 'mobile'])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_revoking_display_rules_hides_listings_immediately(): void
    {
        $this->syncFixtures();
        $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(2, 'data');

        MlsDisplayRule::query()->update(['allow_public_display' => false]);

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_hidden_addresses_are_not_exposed(): void
    {
        $property = Property::factory()->published()->create(['address_display' => false, 'street_address' => '9 Secret Road']);

        $data = $this->getJson("/api/v1/properties/{$property->slug}")->assertOk()->json('data');

        $this->assertNull($data['address']['street_address']);
        $this->assertNull($data['location']);
        $this->assertStringNotContainsString('Secret Road', json_encode($data));
    }

    public function test_filters_sorting_and_agent_card(): void
    {
        $agent = Agent::factory()->create(['public_phone' => '+1 201 555 0101']);
        AgentLicense::factory()->verified()->create(['agent_id' => $agent->id]);
        Property::factory()->published()->forAgent($agent)->create(['list_price' => 300000, 'bedrooms' => 2, 'city' => 'Newark']);
        Property::factory()->published()->create(['list_price' => 900000, 'bedrooms' => 5, 'city' => 'Hoboken', 'listing_type' => 'rent']);

        $this->getJson('/api/v1/properties?sort=price_desc')->assertOk()->assertJsonPath('data.0.list_price', 900000);
        $this->getJson('/api/v1/properties?min_beds=3')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/properties?city=newark&listing_type=sale')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/properties?min_price=500000&max_price=100')->assertStatus(422);

        $slug = Property::where('city', 'Newark')->value('slug');
        $this->getJson("/api/v1/properties/{$slug}")->assertOk()->assertJsonPath('data.listing_agent.phone', '+1 201 555 0101');
        $this->getJson('/api/v1/property-types')->assertOk()->assertJsonCount(8, 'data');
    }
}
