<?php

namespace Tests\Feature\Agency;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Agency\LicenseStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LicenseVerificationTest extends TestCase
{
    private function actingAsAgent(Agent $agent): void
    {
        Sanctum::actingAs(User::find($agent->user_id)->fresh());
    }

    private function verifyPayload(array $overrides = []): array
    {
        return array_merge([
            'verification_source' => 'state_license_lookup',
            'verification_reference' => 'NJREC licensee search 2026-10-10',
            'expires_on' => now()->addYears(2)->toDateString(),
        ], $overrides);
    }

    public function test_full_workflow_submit_verify_and_publish(): void
    {
        Notification::fake();
        $agent = Agent::factory()->create();
        $this->actingAsAgent($agent);

        $licenseId = $this->postJson('/api/v1/me/agent-profile/licenses', [
            'license_number' => ' test 123456 ',
            'license_type' => 'salesperson',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_verification')
            ->assertJsonPath('data.license_number', 'TEST123456')
            ->json('data.id');

        // Not public until verified.
        $this->getJson("/api/v1/agents/{$agent->slug}")->assertNotFound();

        // The agent cannot verify their own licence.
        $this->postJson("/api/v1/admin/licenses/{$licenseId}/verify", $this->verifyPayload())->assertForbidden();

        $broker = $this->actingAsRole(RoleName::Broker);
        $this->getJson('/api/v1/admin/licenses?status=pending_verification')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/admin/licenses/{$licenseId}/verify", ['verification_source' => 'guess'])->assertStatus(422);
        $this->postJson("/api/v1/admin/licenses/{$licenseId}/verify", $this->verifyPayload())
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.is_currently_valid', true)
            ->assertJsonPath('data.verified_by.id', $broker->id)
            ->assertJsonPath('data.verification_reference', 'NJREC licensee search 2026-10-10');

        Notification::assertSentTo(User::find($agent->user_id), LicenseStatusChangedNotification::class);
        $this->assertTrue(AuditLog::where('event', 'licenses.verified')->where('user_id', $broker->id)->exists());

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/agents/{$agent->slug}")
            ->assertOk()
            ->assertJsonPath('data.license.number', 'TEST123456')
            ->assertJsonPath('data.license.verified', true)
            ->assertJsonMissingPath('data.email_verified');

        // Verified licences cannot be "verified" again or rejected; they can be revoked.
        $this->actingAsRole(RoleName::Broker);
        $this->postJson("/api/v1/admin/licenses/{$licenseId}/reject", ['reason' => 'changed my mind'])
            ->assertStatus(409)->assertJsonPath('error.code', 'license_transition_invalid');
        $this->postJson("/api/v1/admin/licenses/{$licenseId}/revoke", ['reason' => 'Licence suspended by regulator'])
            ->assertOk()->assertJsonPath('data.status', 'revoked');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/agents/{$agent->slug}")->assertNotFound();
    }

    public function test_rejection_requires_a_reason_and_allows_resubmission(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $license = AgentLicense::factory()->create(['license_number' => 'TEST000777']);

        $this->postJson("/api/v1/admin/licenses/{$license->id}/reject", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/licenses/{$license->id}/reject", ['reason' => 'Number not found in state lookup'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        // A rejected number frees the slot for a corrected submission.
        $this->postJson("/api/v1/admin/agents/{$license->agent_id}/licenses", ['license_number' => 'TEST000777', 'license_type' => 'salesperson'])
            ->assertCreated();
    }

    public function test_duplicate_active_license_numbers_are_refused(): void
    {
        $this->actingAsRole(RoleName::Broker);
        AgentLicense::factory()->verified()->create(['license_number' => 'TEST555']);
        $other = Agent::factory()->create();

        $this->postJson("/api/v1/admin/agents/{$other->id}/licenses", ['license_number' => 'test555', 'license_type' => 'broker'])
            ->assertStatus(409)->assertJsonPath('error.code', 'license_duplicate');
    }

    public function test_super_admin_cannot_verify_own_license(): void
    {
        $admin = $this->actingAsRole(RoleName::SuperAdmin);
        $admin->roles()->attach(Role::where('name', 'broker')->value('id'));
        $agent = Agent::factory()->create(['user_id' => $admin->id]);
        $license = AgentLicense::factory()->create(['agent_id' => $agent->id]);

        $this->postJson("/api/v1/admin/licenses/{$license->id}/verify", $this->verifyPayload())
            ->assertForbidden()->assertJsonPath('error.code', 'license_self_verification');
    }

    public function test_office_manager_can_see_queue_but_not_verify(): void
    {
        $this->actingAsRole(RoleName::OfficeManager);
        $license = AgentLicense::factory()->create();

        $this->getJson('/api/v1/admin/licenses')->assertOk();
        $this->postJson("/api/v1/admin/licenses/{$license->id}/verify", $this->verifyPayload())->assertForbidden();
    }

    public function test_past_expiration_cannot_be_verified(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $license = AgentLicense::factory()->create();

        $this->postJson("/api/v1/admin/licenses/{$license->id}/verify", $this->verifyPayload(['expires_on' => now()->subDay()->toDateString()]))
            ->assertStatus(422);
    }

    public function test_daily_job_expires_licenses_and_hides_agents(): void
    {
        Notification::fake();
        $expiring = AgentLicense::factory()->verified(now()->subDays(2)->toDateString())->create();
        $valid = AgentLicense::factory()->verified()->create();

        // Expired by date: already hidden from the directory even before the job runs.
        $this->getJson("/api/v1/agents/{$expiring->agent->slug}")->assertNotFound();
        $this->getJson("/api/v1/agents/{$valid->agent->slug}")->assertOk();

        $this->artisan('hitlease:licenses:expire')->expectsOutputToContain('Expired 1 licence(s).')->assertSuccessful();

        $this->assertSame('expired', $expiring->fresh()->status->value);
        $this->assertSame('verified', $valid->fresh()->status->value);
        Notification::assertSentTo(User::find($expiring->agent->user_id), LicenseStatusChangedNotification::class);
        $this->assertTrue(AuditLog::where('event', 'licenses.expired')->whereNull('user_id')->exists());
    }

    public function test_agents_cannot_submit_licenses_for_others(): void
    {
        $mine = Agent::factory()->create();
        $theirs = Agent::factory()->create();
        $this->actingAsAgent($mine);

        $this->postJson("/api/v1/admin/agents/{$theirs->id}/licenses", ['license_number' => 'TEST1', 'license_type' => 'salesperson'])
            ->assertForbidden();
    }
}
