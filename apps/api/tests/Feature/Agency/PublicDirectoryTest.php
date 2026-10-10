<?php

namespace Tests\Feature\Agency;

use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\Office;
use App\Models\User;
use Tests\TestCase;

class PublicDirectoryTest extends TestCase
{
    private function listedAgent(array $agent = [], array $user = []): Agent
    {
        $model = Agent::factory()->create($agent);
        if ($user !== []) {
            User::find($model->user_id)->forceFill($user)->save();
        }
        AgentLicense::factory()->verified()->create(['agent_id' => $model->id]);

        return $model;
    }

    public function test_only_eligible_agents_are_listed(): void
    {
        $visible = $this->listedAgent();
        $this->listedAgent(['is_public' => false]);
        $this->listedAgent(['status' => 'inactive']);
        $this->listedAgent(user: ['status' => 'deactivated']);
        Agent::factory()->create(); // no licence
        AgentLicense::factory()->create(); // pending licence only

        $this->getJson('/api/v1/agents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $visible->slug);
    }

    public function test_filters_and_private_fields(): void
    {
        $office = Office::factory()->create(['city' => 'Hoboken']);
        $spanish = $this->listedAgent(['languages' => ['en', 'es'], 'office_id' => $office->id, 'agency_id' => $office->agency_id], ['first_name' => 'Lucia']);
        $this->listedAgent(['languages' => ['en']], ['first_name' => 'Zed']);

        $this->getJson('/api/v1/agents?language=es')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $spanish->slug);
        $this->getJson('/api/v1/agents?city=hoboken')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/agents?search=luc')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/agents?language=xx')->assertStatus(422);

        $profile = $this->getJson("/api/v1/agents/{$spanish->slug}")->assertOk()->json('data');
        $this->assertSame('Hoboken', $profile['office']['city']);
        $this->assertArrayNotHasKey('user_id', $profile);
        $this->assertArrayNotHasKey('status', $profile);
        $this->assertStringNotContainsString(User::find($spanish->user_id)->email, json_encode($profile));
    }
}
