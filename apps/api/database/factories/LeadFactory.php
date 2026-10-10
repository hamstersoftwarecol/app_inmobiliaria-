<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        $email = 'lead'.fake()->unique()->numberBetween(1000, 999999).'@example.com';

        return [
            'source' => 'website_contact',
            'intent' => 'buy',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => $email,
            'email_key' => $email,
            'message' => 'Fictitious inquiry used by tests.',
        ];
    }
}
