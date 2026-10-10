<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentLicense>
 */
class AgentLicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'license_number' => 'TEST'.fake()->unique()->numerify('######'),
            'license_state' => 'NJ',
            'license_type' => 'salesperson',
            'status' => 'pending_verification',
            'expires_on' => now()->addYear()->toDateString(),
        ];
    }

    public function verified(?string $expiresOn = null): static
    {
        return $this->state(fn () => [
            'status' => 'verified',
            'verified_at' => now(),
            'verification_source' => 'state_license_lookup',
            'verification_reference' => 'test lookup',
            'expires_on' => $expiresOn ?? now()->addYear()->toDateString(),
        ]);
    }
}
