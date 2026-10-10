<?php

namespace Tests\Feature\Admin;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use LogicException;
use Tests\TestCase;

class RolesAndAuditTest extends TestCase
{
    public function test_seeder_is_idempotent_and_complete(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(RoleName::cases()), Role::count());
        $this->assertSame(count(Permission::cases()), Role::where('name', 'super_admin')->first()->permissions()->count());
        $this->assertFalse(Role::where('name', 'broker')->first()->permissions()->where('name', 'mls.manage')->exists());
        $this->assertTrue(Role::where('name', 'buyer')->first()->permissions()->where('name', 'favorites.manage')->exists());
    }

    public function test_seeder_preserves_customized_role_permissions(): void
    {
        $agent = Role::where('name', 'agent')->first();
        $agent->permissions()->detach();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(0, $agent->permissions()->count());
    }

    public function test_broker_can_view_but_not_change_roles(): void
    {
        $this->actingAsRole(RoleName::Broker);

        $this->getJson('/api/v1/admin/roles')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/v1/admin/permissions')->assertOk();
        $this->putJson('/api/v1/admin/roles/'.Role::where('name', 'agent')->value('id').'/permissions', ['permissions' => []])
            ->assertForbidden();
    }

    public function test_super_admin_updates_role_permissions_but_not_super_admin_role(): void
    {
        $this->actingAsRole(RoleName::SuperAdmin);
        $agentRole = Role::where('name', 'agent')->first();

        $this->putJson("/api/v1/admin/roles/{$agentRole->id}/permissions", ['permissions' => ['messages.use', 'leads.create']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['leads.create', 'messages.use']);

        $agent = $this->userWithRole(RoleName::Agent);
        $this->assertTrue($agent->hasPermission(Permission::LeadsCreate));
        $this->assertFalse($agent->hasPermission(Permission::PropertiesCreate));

        $this->putJson('/api/v1/admin/roles/'.Role::where('name', 'super_admin')->value('id').'/permissions', ['permissions' => []])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'role_locked');

        $this->putJson("/api/v1/admin/roles/{$agentRole->id}/permissions", ['permissions' => ['made.up']])->assertStatus(422);
        $this->assertTrue(AuditLog::where('event', 'roles.permissions_updated')->exists());
    }

    public function test_audit_logs_are_filterable_and_immutable(): void
    {
        $this->actingAsRole(RoleName::SuperAdmin);
        app(AuditLogger::class)->log(AuditCategory::Admin, 'test.event', metadata: ['password' => 'secret', 'nested' => ['token' => 'abc', 'ok' => 1]]);

        $response = $this->getJson('/api/v1/admin/audit-logs?category=admin&event=test.')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertEquals(['password' => '[REDACTED]', 'nested' => ['token' => '[REDACTED]', 'ok' => 1]], $response->json('data.0.metadata'));

        $log = AuditLog::firstOrFail();
        $this->expectException(LogicException::class);
        $log->update(['event' => 'tampered']);
    }
}
