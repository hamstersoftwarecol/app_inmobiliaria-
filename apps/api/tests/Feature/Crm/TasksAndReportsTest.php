<?php

namespace Tests\Feature\Crm;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Crm\TaskReminderNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TasksAndReportsTest extends TestCase
{
    public function test_tasks_belong_to_their_assignee_unless_managed(): void
    {
        $agent = Agent::factory()->create();
        $agentUser = User::find($agent->user_id)->fresh();
        $lead = Lead::factory()->create(['assigned_agent_id' => $agent->id]);
        $foreign = Lead::factory()->create();
        $colleague = $this->userWithRole(RoleName::Agent);
        Sanctum::actingAs($agentUser);

        $id = $this->postJson('/api/v1/crm/tasks', ['title' => 'Call back', 'due_at' => now()->addHour()->toIso8601String(), 'lead_id' => $lead->id])
            ->assertCreated()->assertJsonPath('data.lead.id', $lead->id)->assertJsonPath('data.assignee.id', $agentUser->id)->json('data.id');
        $this->postJson('/api/v1/crm/tasks', ['title' => 'Snoop', 'due_at' => now()->addHour()->toIso8601String(), 'lead_id' => $foreign->id])->assertForbidden();
        $this->postJson('/api/v1/crm/tasks', ['title' => 'Delegate', 'due_at' => now()->addHour()->toIso8601String(), 'assigned_to' => $colleague->id])->assertForbidden();
        $this->getJson('/api/v1/crm/tasks?scope=all')->assertForbidden();

        $overdue = CrmTask::query()->create(['assigned_to' => $agentUser->id, 'title' => 'Overdue follow-up', 'due_at' => now()->subDay()]);
        $this->getJson('/api/v1/crm/tasks?state=overdue')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_overdue', true);
        $this->getJson('/api/v1/crm/tasks')->assertOk()->assertJsonPath('data.0.id', $overdue->id); // due first

        $this->patchJson("/api/v1/crm/tasks/{$id}", ['completed' => true])->assertOk()->assertJsonPath('data.is_overdue', false);
        $this->getJson('/api/v1/crm/tasks?state=completed')->assertOk()->assertJsonPath('data.0.id', $id);

        // Someone else's task is off limits; a broker manages everyone's.
        $theirs = CrmTask::query()->create(['assigned_to' => $colleague->id, 'title' => 'Theirs', 'due_at' => now()->addDay()]);
        $this->patchJson("/api/v1/crm/tasks/{$theirs->id}", ['title' => 'Mine now'])->assertForbidden();
        $this->deleteJson("/api/v1/crm/tasks/{$theirs->id}")->assertForbidden();

        $this->actingAsRole(RoleName::Broker);
        $this->patchJson("/api/v1/crm/tasks/{$theirs->id}", ['assigned_to' => $agentUser->id])->assertOk()->assertJsonPath('data.assignee.id', $agentUser->id);
        $this->getJson('/api/v1/crm/tasks?scope=all')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_reminders_are_sent_once_and_again_after_rescheduling(): void
    {
        Notification::fake();
        $user = $this->userWithRole(RoleName::Agent);
        $dueSoon = CrmTask::query()->create(['assigned_to' => $user->id, 'title' => 'Showing prep', 'due_at' => now()->addMinutes(10)]);
        CrmTask::query()->create(['assigned_to' => $user->id, 'title' => 'Later', 'due_at' => now()->addDay()]);
        $done = CrmTask::query()->create(['assigned_to' => $user->id, 'title' => 'Done', 'due_at' => now()->subHour()]);
        $done->forceFill(['completed_at' => now()])->save();

        $this->artisan('app-inmobiliaria:crm:task-reminders')->expectsOutputToContain('Sent 1 reminder(s).');
        $this->artisan('app-inmobiliaria:crm:task-reminders')->expectsOutputToContain('Sent 0 reminder(s).');
        Notification::assertSentToTimes($user, TaskReminderNotification::class, 1);

        Sanctum::actingAs($user);
        $this->patchJson("/api/v1/crm/tasks/{$dueSoon->id}", ['due_at' => now()->addMinutes(5)->toIso8601String()])->assertOk();
        $this->artisan('app-inmobiliaria:crm:task-reminders')->expectsOutputToContain('Sent 1 reminder(s).');
    }

    public function test_report_summary_uses_agency_days_and_requires_reports_permission(): void
    {
        $agent = Agent::factory()->create();
        $this->travelTo(Carbon::parse('2027-04-10 14:00:00', 'UTC'));
        $won = Lead::factory()->create(['assigned_agent_id' => $agent->id, 'source' => 'phone']);
        $won->forceFill(['status' => 'won', 'first_contacted_at' => now()->addMinutes(30)])->save();
        $open = Lead::factory()->create(['assigned_agent_id' => $agent->id]);
        $open->forceFill(['first_contacted_at' => now()->addMinutes(90)])->save();
        Lead::factory()->create(); // unassigned
        // 02:00 UTC on April 11 is still April 10 in New Jersey.
        $this->travelTo(Carbon::parse('2027-04-11 02:00:00', 'UTC'));
        Lead::factory()->create();
        $this->travelTo(Carbon::parse('2027-04-12 12:00:00', 'UTC'));
        Lead::factory()->create(); // outside the range

        $this->actingAsRole(RoleName::Broker);
        $data = $this->getJson('/api/v1/crm/reports/summary?from=2027-04-10&to=2027-04-10')->assertOk()->json('data');

        $this->assertSame(4, $data['total']);
        $this->assertSame(1, $data['won']);
        $this->assertEquals(0.25, $data['conversion_rate']);
        $this->assertEquals(60.0, $data['median_first_response_minutes']);
        $this->assertSame(3, $data['by_status']['new']);
        $this->assertSame(['website_contact' => 3, 'phone' => 1], $data['by_source']);
        $this->assertSame(2, $data['by_agent'][0]['assigned']);
        $this->assertSame(1, $data['by_agent'][0]['won']);

        $this->getJson('/api/v1/crm/reports/summary?from=2025-01-01&to=2027-04-10')->assertUnprocessable()->assertJsonPath('error.code', 'range_too_large');

        Sanctum::actingAs(User::find($agent->user_id)->fresh());
        $this->getJson('/api/v1/crm/reports/summary')->assertForbidden();
    }

    public function test_seeder_grants_new_permissions_without_undoing_customizations(): void
    {
        $broker = Role::query()->where('name', 'broker')->firstOrFail();
        // A super admin revoked an existing permission from brokers…
        $broker->permissions()->detach(Permission::query()->where('name', 'leads.assign')->value('id'));
        // …and a release introduces a permission brokers get by default.
        Permission::query()->where('name', 'leads.delete')->delete();

        $this->seed(RolesAndPermissionsSeeder::class);

        $names = $broker->permissions()->pluck('name');
        $this->assertContains('leads.delete', $names);
        $this->assertNotContains('leads.assign', $names);
        $this->assertNotContains('leads.delete', Role::query()->where('name', 'agent')->firstOrFail()->permissions()->pluck('name'));
    }
}
