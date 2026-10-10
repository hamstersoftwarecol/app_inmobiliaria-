<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Realty';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->companyEmail(),
            'phone' => '+1 201 555 0100',
            'timezone' => 'America/New_York',
        ];
    }
}
