<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    private function newUser(array $roles, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'last_name' => 'Agent',
            'email' => 'new.agent@example.com',
            'password' => self::STRONG_PASSWORD,
            'roles' => $roles,
        ], $overrides);
    }

    public function test_consumers_and_agents_cannot_reach_admin_routes(): void
    {
        foreach ([RoleName::Buyer, RoleName::Seller, RoleName::Agent] as $role) {
            $this->actingAsRole($role);

            $this->getJson('/api/v1/admin/users')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
            $this->getJson('/api/v1/admin/roles')->assertForbidden();
            $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
            $this->getJson('/api/v1/admin/mls/providers')->assertForbidden();
        }
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_staff_without_mfa_must_enrol_first(): void
    {
        $broker = User::factory()->role(RoleName::Broker)->create();
        Sanctum::actingAs($broker);

        $this->getJson('/api/v1/admin/users')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'mfa_enrollment_required');

        config(['app_inmobiliaria.security.enforce_staff_mfa' => false]);
        $this->getJson('/api/v1/admin/users')->assertOk();
    }

    public function test_broker_lists_and_filters_users(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $this->userWithRole(RoleName::Agent, ['first_name' => 'Zoe', 'last_name' => 'Smith', 'email' => 'zoe@example.com']);
        $this->userWithRole(RoleName::Buyer, ['email' => 'buyer@example.com']);

        $this->getJson('/api/v1/admin/users?role=agent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'zoe@example.com')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);

        $this->getJson('/api/v1/admin/users?search=zoe smi')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/users?sort=drop table')->assertStatus(422);
    }

    public function test_broker_can_create_agents_but_not_escalate(): void
    {
        $broker = $this->actingAsRole(RoleName::Broker);

        $this->postJson('/api/v1/admin/users', $this->newUser(['agent']))
            ->assertCreated()
            ->assertJsonPath('data.roles.0.name', 'agent');

        $this->assertDatabaseHas('role_user', ['user_id' => User::where('email', 'new.agent@example.com')->value('id'), 'assigned_by' => $broker->id]);

        foreach (['broker', 'super_admin'] as $role) {
            $this->postJson('/api/v1/admin/users', $this->newUser([$role], ['email' => "{$role}@example.com"]))
                ->assertForbidden()
                ->assertJsonPath('error.code', 'role_escalation_denied');
        }
    }

    public function test_office_manager_cannot_create_users(): void
    {
        $this->actingAsRole(RoleName::OfficeManager);

        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->postJson('/api/v1/admin/users', $this->newUser(['buyer']))->assertForbidden();
    }

    public function test_hierarchy_protects_peers_and_superiors(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $otherBroker = $this->userWithRole(RoleName::Broker);
        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $agent = $this->userWithRole(RoleName::Agent);

        $this->patchJson("/api/v1/admin/users/{$otherBroker->id}", ['first_name' => 'X'])->assertForbidden();
        $this->postJson("/api/v1/admin/users/{$admin->id}/deactivate")->assertForbidden();
        $this->putJson("/api/v1/admin/users/{$agent->id}/roles", ['roles' => ['super_admin']])
            ->assertForbidden()->assertJsonPath('error.code', 'role_escalation_denied');

        $this->putJson("/api/v1/admin/users/{$agent->id}/roles", ['roles' => ['agent', 'office_manager']])
            ->assertOk()
            ->assertJsonCount(2, 'data.roles');
    }

    public function test_deactivation_revokes_sessions_and_blocks_requests(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $agent = $this->userWithRole(RoleName::Agent);
        $token = $agent->createToken('phone')->plainTextToken;

        $this->postJson("/api/v1/admin/users/{$agent->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'deactivated');

        $this->assertSame(0, $agent->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->assertTrue(AuditLog::where('event', 'users.deactivated')->where('auditable_id', $agent->id)->exists());
    }

    public function test_active_middleware_rejects_deactivated_sessions(): void
    {
        $user = User::factory()->role(RoleName::Buyer)->deactivated()->create();
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('error.code', 'account_deactivated');
    }

    public function test_users_cannot_manage_themselves_through_admin_api(): void
    {
        $admin = $this->actingAsRole(RoleName::SuperAdmin);

        $this->postJson("/api/v1/admin/users/{$admin->id}/deactivate")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'self_modification_denied');
    }

    public function test_admin_update_is_audited_without_secrets(): void
    {
        $this->actingAsRole(RoleName::SuperAdmin);
        $agent = $this->userWithRole(RoleName::Agent, ['first_name' => 'Old']);

        $this->patchJson("/api/v1/admin/users/{$agent->id}", ['first_name' => 'New'])->assertOk();

        $log = AuditLog::where('event', 'users.updated')->firstOrFail();
        $this->assertSame(['first_name' => 'Old'], $log->old_values);
        $this->assertSame(['first_name' => 'New'], $log->new_values);
    }
}
