<?php

namespace Tests\Feature\Agency;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\Office;
use App\Models\User;
use App\Support\AgencyClock;
use Tests\TestCase;

class AgentAdministrationTest extends TestCase
{
    public function test_broker_creates_an_agent_profile_for_an_agent_user(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $office = Office::factory()->create();
        $user = $this->userWithRole(RoleName::Agent, ['first_name' => 'Ana', 'last_name' => 'Ruiz']);

        $this->postJson('/api/v1/admin/agents', [
            'user_id' => $user->id,
            'agency_id' => $office->agency_id,
            'office_id' => $office->id,
            'languages' => ['en', 'es'],
            'specialties' => ['first_time_buyers'],
            'is_public' => true,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'ana-ruiz')
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonPath('data.license_verified', false);

        // Same user twice, wrong roles and foreign offices are refused.
        $this->postJson('/api/v1/admin/agents', ['user_id' => $user->id, 'agency_id' => $office->agency_id])
            ->assertStatus(409)->assertJsonPath('error.code', 'agent_profile_exists');

        $buyer = $this->userWithRole(RoleName::Buyer);
        $this->postJson('/api/v1/admin/agents', ['user_id' => $buyer->id, 'agency_id' => $office->agency_id])
            ->assertStatus(422)->assertJsonPath('error.code', 'agent_user_not_eligible');

        $other = $this->userWithRole(RoleName::Agent);
        $foreignOffice = Office::factory()->create();
        $this->postJson('/api/v1/admin/agents', ['user_id' => $other->id, 'agency_id' => $office->agency_id, 'office_id' => $foreignOffice->id])
            ->assertStatus(422)->assertJsonPath('error.code', 'office_outside_agency');

        $this->postJson('/api/v1/admin/agents', ['user_id' => $other->id, 'agency_id' => $office->agency_id, 'languages' => ['klingon']])
            ->assertStatus(422);
    }

    public function test_listing_filters_by_license_state(): void
    {
        $this->actingAsRole(RoleName::OfficeManager);
        $verified = AgentLicense::factory()->verified()->create()->agent;
        $pending = AgentLicense::factory()->create()->agent;
        $expired = AgentLicense::factory()->verified(AgencyClock::today()->subDay()->toDateString())->create()->agent;

        $ids = fn (string $filter) => collect($this->getJson("/api/v1/admin/agents?license={$filter}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$verified->id], $ids('verified'));
        $this->assertSame([$pending->id], $ids('pending'));
        $this->assertSame(collect([$pending->id, $expired->id])->sort()->values()->all(), $ids('none'));
    }

    public function test_office_manager_can_view_but_not_change_agents(): void
    {
        $this->actingAsRole(RoleName::OfficeManager);
        $agent = Agent::factory()->create();

        $this->getJson("/api/v1/admin/agents/{$agent->id}")->assertOk();
        $this->patchJson("/api/v1/admin/agents/{$agent->id}", ['status' => 'inactive'])->assertForbidden();
    }

    public function test_broker_deactivates_agent(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $agent = Agent::factory()->create();

        $this->patchJson("/api/v1/admin/agents/{$agent->id}", ['status' => 'inactive', 'bio' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.bio', 'Updated');

        $this->patchJson("/api/v1/admin/agents/{$agent->id}", ['user_id' => 99])->assertStatus(422);
    }

    public function test_agent_self_service_is_limited_to_own_public_fields(): void
    {
        $agent = Agent::factory()->create();
        $office = Office::factory()->create(['agency_id' => $agent->agency_id]);
        $this->actingAs(User::find($agent->user_id)->fresh(), 'sanctum');

        $this->getJson('/api/v1/me/agent-profile')->assertOk()->assertJsonPath('data.id', $agent->id);

        $this->patchJson('/api/v1/me/agent-profile', [
            'bio' => 'I help first-time buyers.',
            'languages' => ['en', 'es'],
            'office_id' => $office->id,
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.bio', 'I help first-time buyers.')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.office', null);

        // Agents cannot reach the admin agent API or another agent's record.
        $this->getJson('/api/v1/admin/agents')->assertForbidden();
        $this->patchJson('/api/v1/admin/agents/'.Agent::factory()->create()->id, ['bio' => 'x'])->assertForbidden();
    }

    public function test_users_without_profile_get_a_clear_error(): void
    {
        $this->actingAsRole(RoleName::Agent);
        $this->getJson('/api/v1/me/agent-profile')->assertNotFound()->assertJsonPath('error.code', 'agent_profile_missing');

        $this->actingAsRole(RoleName::Buyer);
        $this->getJson('/api/v1/me/agent-profile')->assertNotFound();
    }

    public function test_agency_factory_relations_are_consistent(): void
    {
        $agency = Agency::factory()->has(Office::factory()->count(2))->create();
        $this->assertCount(2, $agency->offices);
    }
}
