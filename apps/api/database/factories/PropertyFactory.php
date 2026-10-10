<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'test-property-'.Str::lower(Str::random(10)),
            'source' => 'internal',
            'title' => 'Test listing '.fake()->numberBetween(1, 9999),
            'listing_type' => 'sale',
            'status' => 'active',
            'property_type_id' => fn () => PropertyType::query()->where('code', 'single_family')->value('id'),
            'list_price' => 550000,
            'street_address' => fake()->buildingNumber().' Test Street',
            'city' => 'Hoboken',
            'county' => 'Hudson',
            'state' => 'NJ',
            'postal_code' => '07030',
            'latitude' => 40.744,
            'longitude' => -74.0324,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'is_published' => false,
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn () => ['listing_agent_id' => $agent->id, 'agency_id' => $agent->agency_id]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true, 'published_at' => now()]);
    }
}
