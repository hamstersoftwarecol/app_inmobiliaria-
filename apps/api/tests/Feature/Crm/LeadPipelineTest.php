<?php

namespace Tests\Feature\Crm;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Property;
use App\Models\User;
use App\Notifications\Crm\LeadAssignedNotification;
use App\Notifications\Crm\UnassignedLeadNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
{
    private function inquiry(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Pat', 'last_name' => 'Prospect', 'email' => 'Pat.Prospect@Example.com', 'phone' => '(201) 555-0142',
            'message' => 'I would like to see this home on Saturday.', 'intent' => 'buy', 'contact_consent' => true,
        ], $overrides);
    }

    private function actingAsAgent(?Agent $agent = null): Agent
    {
        $agent ??= Agent::factory()->create();
        Sanctum::actingAs(User::find($agent->user_id)->fresh());

        return $agent;
    }

    public function test_listing_inquiry_creates_a_lead_assigned_to_the_listing_agent(): void
    {
        Notification::fake();
        $agent = Agent::factory()->create();
        $listing = Property::factory()->forAgent($agent)->published()->create();

        $this->postJson('/api/v1/inquiries', $this->inquiry(['property_slug' => $listing->slug]))
            ->assertStatus(202)->assertJsonPath('message', 'Thank you. An agent will contact you shortly.');

        $lead = Lead::query()->sole();
        $this->assertSame('website_listing', $lead->source->value);
        $this->assertSame($agent->id, $lead->assigned_agent_id);
        $this->assertSame('pat.prospect@example.com', $lead->email_key);
        $this->assertSame('2015550142', $lead->phone_key);
        $this->assertTrue($lead->contact_consent);
        $this->assertSame('127.0.0.1', $lead->consent_ip);
        $this->assertSame(['inquiry', 'assignment'], $lead->activities()->pluck('type')->map->value->sort()->values()->reverse()->values()->all());
        Notification::assertSentTo(User::find($agent->user_id), LeadAssignedNotification::class, fn ($n, $channels) => $channels === ['mail', 'database']);
    }

    public function test_repeat_inquiries_from_the_same_person_join_the_open_lead(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/inquiries', $this->inquiry())->assertStatus(202);
        $this->postJson('/api/v1/inquiries', $this->inquiry(['email' => null, 'phone' => '+1 201-555-0142', 'message' => 'Second question']))->assertStatus(202);
        $this->postJson('/api/v1/inquiries', $this->inquiry(['email' => 'PAT.prospect@example.com ', 'phone' => null]))->assertStatus(202);

        $this->assertSame(1, Lead::query()->count());
        $this->assertSame(3, LeadActivity::query()->where('type', 'inquiry')->count());

        // Closed leads are not reopened by a new inquiry: a new lead starts.
        Lead::query()->sole()->forceFill(['status' => 'lost'])->save();
        $this->postJson('/api/v1/inquiries', $this->inquiry())->assertStatus(202);
        $this->assertSame(2, Lead::query()->count());
    }

    public function test_unassigned_inquiries_notify_lead_managers_in_app(): void
    {
        Notification::fake();
        $broker = $this->userWithRole(RoleName::Broker);
        $agentUser = $this->userWithRole(RoleName::Agent);

        $this->postJson('/api/v1/inquiries', $this->inquiry(['email' => 'general@example.com']))->assertStatus(202);

        $this->assertNull(Lead::query()->sole()->assigned_agent_id);
        Notification::assertSentTo($broker, UnassignedLeadNotification::class, fn ($n, $channels) => $channels === ['database']);
        Notification::assertNotSentTo($agentUser, UnassignedLeadNotification::class);
    }

    public function test_inquiry_validation_consent_honeypot_and_hidden_targets(): void
    {
        $this->postJson('/api/v1/inquiries', $this->inquiry(['contact_consent' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors(['contact_consent'], 'error.details.fields');
        $this->postJson('/api/v1/inquiries', $this->inquiry(['email' => null, 'phone' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone'], 'error.details.fields');

        // Bots filling the hidden field get the normal answer but nothing is stored.
        $this->postJson('/api/v1/inquiries', $this->inquiry(['website' => 'http://spam.example']))->assertStatus(202);
        $this->assertDatabaseCount('leads', 0);

        $draft = Property::factory()->create();
        $this->postJson('/api/v1/inquiries', $this->inquiry(['property_slug' => $draft->slug]))->assertNotFound();

        // Agent profile inquiries go to that agent once their licence is verified and they are public.
        $agent = Agent::factory()->create();
        AgentLicense::factory()->for($agent)->verified(now()->addYear()->toDateString())->create();
        $this->postJson('/api/v1/inquiries', $this->inquiry(['agent_slug' => $agent->slug, 'email' => 'agentpage@example.com']))->assertStatus(202);
        $this->assertSame($agent->id, Lead::query()->where('email_key', 'agentpage@example.com')->value('assigned_agent_id'));
    }

    public function test_inquiries_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/inquiries', $this->inquiry(['email' => "rl{$i}@example.com"]))->assertStatus(202);
        }
        $this->postJson('/api/v1/inquiries', $this->inquiry(['email' => 'rl6@example.com']))->assertStatus(429);
    }

    public function test_agents_only_see_and_update_their_own_leads(): void
    {
        $agent = Agent::factory()->create();
        $mine = Lead::factory()->create(['assigned_agent_id' => $agent->id]);
        $other = Lead::factory()->create();
        $this->actingAsAgent($agent);

        $this->getJson('/api/v1/crm/leads')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/v1/crm/leads/{$other->id}")->assertForbidden();
        $this->patchJson("/api/v1/crm/leads/{$other->id}", ['first_name' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/crm/leads/{$mine->id}", ['phone' => '201 555 0199'])->assertOk()->assertJsonPath('data.phone', '201 555 0199');
        $this->assertSame('2015550199', $mine->fresh()->phone_key);
        $this->postJson("/api/v1/crm/leads/{$mine->id}/assign", ['agent_id' => null])->assertForbidden();
        $this->deleteJson("/api/v1/crm/leads/{$mine->id}")->assertForbidden();

        $board = $this->getJson('/api/v1/crm/leads/board')->assertOk()->json('data');
        $this->assertSame(['new', 'contacted', 'qualified', 'showing', 'negotiating', 'won', 'lost'], array_column($board, 'status'));
        $this->assertSame(1, $board[0]['total']);

        $this->actingAsRole(RoleName::Buyer);
        $this->getJson('/api/v1/crm/leads')->assertForbidden();
    }

    public function test_agents_create_leads_for_themselves_and_brokers_assign(): void
    {
        Notification::fake();
        $agent = $this->actingAsAgent();
        $id = $this->postJson('/api/v1/crm/leads', ['first_name' => 'Walk', 'last_name' => 'In', 'phone' => '201-555-0100', 'source' => 'walk_in', 'assigned_agent_id' => 999])
            ->assertCreated()->assertJsonPath('data.assigned_agent.id', $agent->id)->json('data.id');
        Notification::assertNothingSent(); // no self-notification
        $this->postJson('/api/v1/crm/leads', ['first_name' => 'No', 'source' => 'phone'])->assertUnprocessable()->assertJsonValidationErrors(['email'], 'error.details.fields');
        $this->postJson('/api/v1/crm/leads', ['first_name' => 'Web', 'email' => 'w@example.com', 'source' => 'website_listing'])->assertUnprocessable();

        $broker = $this->actingAsRole(RoleName::Broker);
        $other = Agent::factory()->create();
        $inactive = Agent::factory()->create(['status' => 'inactive']);
        $this->postJson("/api/v1/crm/leads/{$id}/assign", ['agent_id' => $inactive->id])->assertUnprocessable()->assertJsonPath('error.code', 'lead_agent_invalid');
        $this->postJson("/api/v1/crm/leads/{$id}/assign", ['agent_id' => $other->id])->assertOk()->assertJsonPath('data.assigned_agent.id', $other->id);

        Notification::assertSentTo(User::find($other->user_id), LeadAssignedNotification::class);
        $this->assertTrue(AuditLog::query()->where('event', 'leads.assigned')->where('user_id', $broker->id)->exists());
    }

    public function test_status_changes_and_activity_logging(): void
    {
        $agent = Agent::factory()->create();
        $lead = Lead::factory()->create(['assigned_agent_id' => $agent->id]);
        $this->actingAsAgent($agent);

        $this->postJson("/api/v1/crm/leads/{$lead->id}/activities", ['type' => 'note'])->assertUnprocessable();
        $this->postJson("/api/v1/crm/leads/{$lead->id}/activities", ['type' => 'inquiry', 'body' => 'x'])->assertUnprocessable();
        $this->postJson("/api/v1/crm/leads/{$lead->id}/activities", ['type' => 'call', 'body' => 'Left a voicemail', 'occurred_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();

        // A logged call is the first contact and moves a new lead to "contacted".
        $this->postJson("/api/v1/crm/leads/{$lead->id}/activities", ['type' => 'call', 'body' => 'Discussed budget'])
            ->assertCreated()->assertJsonPath('data.type', 'call');
        $lead->refresh();
        $this->assertSame('contacted', $lead->status->value);
        $this->assertNotNull($lead->first_contacted_at);

        $this->postJson("/api/v1/crm/leads/{$lead->id}/status", ['status' => 'lost'])->assertUnprocessable()->assertJsonValidationErrors(['lost_reason'], 'error.details.fields');
        $this->postJson("/api/v1/crm/leads/{$lead->id}/status", ['status' => 'lost', 'lost_reason' => 'Bought elsewhere'])
            ->assertOk()->assertJsonPath('data.status', 'lost')->assertJsonPath('data.lost_reason', 'Bought elsewhere');
        $this->assertNotNull($lead->fresh()->closed_at);
        $this->postJson("/api/v1/crm/leads/{$lead->id}/status", ['status' => 'qualified'])->assertOk()->assertJsonPath('data.lost_reason', null);
        $this->assertNull($lead->fresh()->closed_at);

        $timeline = $this->getJson("/api/v1/crm/leads/{$lead->id}")->assertOk()->json('data.activities');
        $this->assertSame(['status_change', 'status_change', 'status_change', 'call'], array_column($timeline, 'type'));

        $this->expectException(LogicException::class);
        LeadActivity::query()->first()->update(['body' => 'rewritten']);
    }

    public function test_managers_merge_duplicates(): void
    {
        $broker = $this->actingAsRole(RoleName::Broker);
        $keep = Lead::factory()->create(['email' => 'dup@example.com', 'email_key' => 'dup@example.com', 'phone' => null]);
        $dupe = Lead::factory()->create(['email' => 'DUP@example.com', 'email_key' => 'dup@example.com', 'phone' => '201 555 0177', 'phone_key' => '2015550177']);
        LeadActivity::query()->create(['lead_id' => $dupe->id, 'type' => 'note', 'body' => 'From the duplicate', 'occurred_at' => now()]);

        $this->getJson("/api/v1/crm/leads/{$keep->id}")->assertOk()->assertJsonPath('meta.possible_duplicates.0.id', $dupe->id);

        $this->postJson("/api/v1/crm/leads/{$keep->id}/merge", ['into_lead_id' => $keep->id])->assertUnprocessable()->assertJsonPath('error.code', 'lead_merge_invalid');
        $this->postJson("/api/v1/crm/leads/{$dupe->id}/merge", ['into_lead_id' => $keep->id])->assertOk()->assertJsonPath('data.phone', '201 555 0177');

        $this->assertSoftDeleted($dupe);
        $this->assertSame(['merge', 'note'], $keep->activities()->pluck('type')->map->value->all());
        $this->assertTrue(AuditLog::query()->where('event', 'leads.merged')->where('user_id', $broker->id)->exists());

        $this->deleteJson("/api/v1/crm/leads/{$keep->id}")->assertNoContent();
        $this->assertSoftDeleted($keep);
        $this->getJson("/api/v1/crm/leads/{$keep->id}")->assertNotFound();
    }

    public function test_staff_must_enrol_in_mfa_to_use_the_crm(): void
    {
        Sanctum::actingAs(User::factory()->role(RoleName::OfficeManager)->create());
        $this->getJson('/api/v1/crm/leads')->assertForbidden()->assertJsonPath('error.code', 'mfa_enrollment_required');
    }
}
