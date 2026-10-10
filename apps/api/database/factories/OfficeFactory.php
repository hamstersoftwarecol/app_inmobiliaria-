<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => 'Office '.fake()->unique()->city(),
            'slug' => 'office-'.Str::lower(Str::random(8)),
            'street_address' => fake()->buildingNumber().' Test Street',
            'city' => 'Hackensack',
            'county' => 'Bergen',
            'state' => 'NJ',
            'postal_code' => '07601',
            'latitude' => 40.8859,
            'longitude' => -74.0435,
            'is_active' => true,
        ];
    }
}
