<?php

namespace Tests\Feature\Notifications;

use App\Application\Notifications\NotificationPreferences;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Models\AuditLog;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Notifications\Crm\LeadAssignedNotification;
use App\Notifications\Crm\TaskReminderNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    public function test_in_app_notifications_list_count_and_read(): void
    {
        $user = $this->actingAsRole(RoleName::Agent);
        $task = CrmTask::query()->create(['assigned_to' => $user->id, 'title' => 'Call back', 'due_at' => now()->addMinute()]);
        $user->notifyNow(new TaskReminderNotification($task), ['database']);
        $user->notifyNow(new TaskReminderNotification($task), ['database']);

        $this->getJson('/api/v1/me/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 2);
        $items = $this->getJson('/api/v1/me/notifications')->assertOk()->assertJsonPath('data.0.type', 'crm.task_due')->json('data');
        $this->postJson("/api/v1/me/notifications/{$items[0]['id']}/read")->assertOk()->assertJsonPath('data.count', 1);
        $this->getJson('/api/v1/me/notifications?unread=1')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/me/notifications/read-all')->assertOk()->assertJsonPath('data.count', 0);

        // Someone else's notification cannot be touched.
        $this->actingAsRole(RoleName::Agent);
        $this->postJson("/api/v1/me/notifications/{$items[1]['id']}/read")->assertNotFound();
    }

    public function test_mail_preferences_control_optional_emails_only(): void
    {
        $user = $this->actingAsRole(RoleName::Agent);
        $prefs = $this->getJson('/api/v1/me/notification-preferences')->assertOk()->json('data');
        $this->assertTrue($prefs['lead_updates']['mail']);
        $this->assertFalse($prefs['marketing']['mail']); // opt-in only

        $lead = Lead::factory()->create();
        $this->assertSame(['database', 'mail'], (new LeadAssignedNotification($lead))->via($user));

        $this->putJson('/api/v1/me/notification-preferences', ['mail' => ['lead_updates' => false]])->assertOk()->assertJsonPath('data.lead_updates.mail', false);
        $this->assertSame(['database'], (new LeadAssignedNotification($lead))->via($user->fresh()));

        $this->putJson('/api/v1/me/notification-preferences', ['mail' => ['password_reset' => false]])->assertUnprocessable();

        // Marketing opt-in is timestamped and audited.
        $this->putJson('/api/v1/me/notification-preferences', ['mail' => ['marketing' => true]])->assertOk()->assertJsonPath('data.marketing.mail', true);
        $this->assertNotNull($this->getJson('/api/v1/me/notification-preferences')->json('data.marketing.consented_at'));
        $this->assertTrue(AuditLog::query()->where('event', 'notifications.marketing_consent')->where('user_id', $user->id)->exists());
    }

    public function test_one_click_unsubscribe_link(): void
    {
        $user = $this->userWithRole(RoleName::Agent);
        $token = app(NotificationPreferences::class)->unsubscribeToken($user, NotificationEvent::TaskReminders);

        $task = CrmTask::query()->create(['assigned_to' => $user->id, 'title' => 'Call back', 'due_at' => now()->addMinute()]);
        $mail = (new TaskReminderNotification($task))->toMail($user);
        $this->assertStringContainsString('/unsubscribe?token=', implode("\n", $mail->outroLines));

        // RFC 8058 one-click: POST with the token in the query string, no session.
        $this->post('/api/v1/notifications/unsubscribe?token='.urlencode($token), ['List-Unsubscribe' => 'One-Click'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('event', 'task_reminders');
        $this->assertFalse(app(NotificationPreferences::class)->wantsMail($user, NotificationEvent::TaskReminders));
        $this->assertTrue(app(NotificationPreferences::class)->wantsMail($user, NotificationEvent::LeadUpdates));

        $this->postJson('/api/v1/notifications/unsubscribe', ['token' => 'forged'])->assertUnprocessable()->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_reminder_mail_respects_preferences_end_to_end(): void
    {
        Notification::fake();
        $user = $this->userWithRole(RoleName::Agent);
        app(NotificationPreferences::class)->set($user, NotificationEvent::TaskReminders, false);
        CrmTask::query()->create(['assigned_to' => $user->id, 'title' => 'Soon', 'due_at' => now()->addMinutes(5)]);

        $this->artisan('app-inmobiliaria:crm:task-reminders');

        Notification::assertSentTo($user, TaskReminderNotification::class, fn ($n, $channels) => $channels === ['database']);
    }
}
