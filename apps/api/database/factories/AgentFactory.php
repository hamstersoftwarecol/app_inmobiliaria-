<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(RoleName::Agent),
            'agency_id' => Agency::factory(),
            'slug' => 'agent-'.Str::lower(Str::random(10)),
            'status' => 'active',
            'is_public' => true,
            'languages' => ['en'],
            'specialties' => ['buyers'],
        ];
    }
}
