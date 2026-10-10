<?php

namespace Tests\Feature\Showings;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\Crm\LeadAssignedNotification;
use App\Notifications\Showings\ShowingReminderNotification;
use App\Notifications\Showings\ShowingUpdateNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowingsTest extends TestCase
{
    private Agent $agent;

    private User $agentUser;

    private Property $listing;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday March 10, 2027, 07:00 in New Jersey (EST; DST starts Sunday March 14).
        $this->travelTo(Carbon::parse('2027-03-10 12:00:00', 'UTC'));
        $this->agent = Agent::factory()->create();
        $this->agentUser = User::find($this->agent->user_id)->fresh();
        foreach (range(1, 7) as $day) {
            $this->agent->availabilities()->create(['weekday' => $day, 'start_time' => '09:00', 'end_time' => '17:00']);
        }
        $this->listing = Property::factory()->forAgent($this->agent)->published()->create(['title' => 'Showing colonial']);
    }

    /** @return list<string> */
    private function slots(string $date): array
    {
        $day = collect($this->getJson("/api/v1/properties/{$this->listing->slug}/showing-slots?from={$date}&days=1")->assertOk()->json('data.days'))->first();

        return array_column($day['slots'], 'starts_at');
    }

    private function requestShowing(string $startsAt, array $extra = [], array $headers = [])
    {
        return $this->postJson('/api/v1/appointments', array_merge(['property_slug' => $this->listing->slug, 'starts_at' => $startsAt, 'contact_consent' => true], $extra), $headers);
    }

    public function test_slots_follow_weekly_hours_notice_time_off_and_bookings(): void
    {
        $this->assertCount(16, $this->slots('2027-03-11'));
        $this->assertSame('2027-03-11T14:00:00+00:00', $this->slots('2027-03-11')[0]); // 09:00 EST

        // Today: 07:00 now + 2 h notice → from 09:00.
        $this->assertSame('2027-03-10T14:00:00+00:00', $this->slots('2027-03-10')[0]);

        $this->agent->timeOff()->create(['starts_at' => '2027-03-11 17:00:00+00', 'ends_at' => '2027-03-11 18:00:00+00']); // 12:00–13:00 local
        Appointment::query()->create([
            'agent_id' => $this->agent->id, 'contact_name' => 'X', 'starts_at' => '2027-03-11 20:00:00+00', 'ends_at' => '2027-03-11 20:30:00+00', 'timezone' => 'America/New_York',
        ]);
        $slots = $this->slots('2027-03-11');
        $this->assertCount(13, $slots);
        $this->assertNotContains('2027-03-11T17:00:00+00:00', $slots);
        $this->assertNotContains('2027-03-11T20:00:00+00:00', $slots);

        // Beyond 30 days and in the past: nothing.
        $this->assertSame([], $this->slots('2027-04-30'));
        $this->getJson("/api/v1/properties/{$this->listing->slug}/showing-slots?from=2027-03-01&days=1")->assertOk()->assertJsonPath('data.days.0.date', '2027-03-10');
    }

    public function test_slots_keep_local_times_across_daylight_saving(): void
    {
        $this->agent->availabilities()->delete();
        $this->agent->availabilities()->create(['weekday' => 7, 'start_time' => '01:00', 'end_time' => '04:00']); // Sunday
        $this->agent->availabilities()->create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '09:30']); // Monday

        // 02:00–03:00 does not exist on March 14, 2027.
        $this->assertSame(
            ['2027-03-14T06:00:00+00:00', '2027-03-14T06:30:00+00:00', '2027-03-14T07:00:00+00:00', '2027-03-14T07:30:00+00:00'],
            $this->slots('2027-03-14'),
        );
        // 09:00 local is 13:00 UTC once daylight time starts (14:00 UTC the week before).
        $this->assertSame(['2027-03-15T13:00:00+00:00'], $this->slots('2027-03-15'));
    }

    public function test_listings_without_online_showings(): void
    {
        $this->listing->forceFill(['status' => 'pending'])->save();
        $this->getJson("/api/v1/properties/{$this->listing->slug}/showing-slots")->assertOk()->assertJsonPath('data.bookable', false);
        $this->actingAsRole(RoleName::Buyer);
        $this->requestShowing('2027-03-11T14:00:00Z')->assertUnprocessable()->assertJsonPath('error.code', 'showings_unavailable');

        $draft = Property::factory()->forAgent($this->agent)->create();
        $this->getJson("/api/v1/properties/{$draft->slug}/showing-slots")->assertNotFound();
    }

    public function test_visitor_requests_a_slot_and_the_agent_confirms(): void
    {
        Notification::fake();
        $buyer = $this->actingAsRole(RoleName::Buyer, ['phone' => '201-555-0101']);

        $this->requestShowing('2027-03-11T14:00:00Z', ['contact_consent' => false])->assertUnprocessable();
        $this->requestShowing('2027-03-11T14:10:00Z')->assertStatus(409)->assertJsonPath('error.code', 'slot_unavailable');

        $id = $this->requestShowing('2027-03-11T14:00:00Z', ['notes' => 'Bringing my partner'])
            ->assertCreated()->assertJsonPath('data.status', 'requested')->assertJsonPath('data.contact', null)
            ->assertJsonPath('data.can.confirm', false)->assertJsonPath('data.can.cancel', true)->json('data.id');

        $lead = Lead::query()->sole();
        $this->assertSame($this->agent->id, $lead->assigned_agent_id);
        $this->assertSame($lead->id, Appointment::find($id)->lead_id);
        Notification::assertNotSentTo($this->agentUser, LeadAssignedNotification::class);
        Notification::assertSentTo($this->agentUser, ShowingUpdateNotification::class, fn ($n) => $n->kind === 'requested');

        // The slot is gone for everyone else.
        $this->assertNotContains('2027-03-11T14:00:00+00:00', $this->slots('2027-03-11'));
        $this->actingAsRole(RoleName::Buyer);
        $this->requestShowing('2027-03-11T14:00:00Z')->assertStatus(409);
        $this->getJson("/api/v1/appointments/{$id}")->assertForbidden();

        Sanctum::actingAs($buyer);
        $this->postJson("/api/v1/appointments/{$id}/confirm")->assertForbidden();

        Sanctum::actingAs($this->agentUser);
        $this->getJson("/api/v1/appointments/{$id}")->assertOk()->assertJsonPath('data.contact.phone', '201-555-0101');
        $this->postJson("/api/v1/appointments/{$id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->postJson("/api/v1/appointments/{$id}/confirm")->assertStatus(409)->assertJsonPath('error.code', 'appointment_invalid_transition');

        Notification::assertSentTo($buyer, ShowingUpdateNotification::class, function ($n) use ($buyer) {
            $mail = $n->toMail($buyer);

            return $n->kind === 'confirmed' && count($mail->rawAttachments) === 1 && str_contains($mail->rawAttachments[0]['data'], 'DTSTART:20270311T140000Z');
        });

        $this->getJson('/api/v1/appointments?role=host')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.property.street_address', $this->listing->street_address);
    }

    public function test_idempotency_key_replays_the_first_response(): void
    {
        Notification::fake();
        $this->actingAsRole(RoleName::Buyer);
        $headers = ['Idempotency-Key' => 'showing-req-0001'];

        $first = $this->requestShowing('2027-03-11T15:00:00Z', [], $headers)->assertCreated()->json('data.id');
        $this->requestShowing('2027-03-11T15:00:00Z', [], $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $first);
        $this->assertSame(1, Appointment::query()->count());

        $this->requestShowing('2027-03-11T15:30:00Z', [], $headers)->assertUnprocessable()->assertJsonPath('error.code', 'idempotency_key_reused');
        $this->requestShowing('2027-03-11T15:30:00Z', [], ['Idempotency-Key' => 'bad key!'])->assertUnprocessable();

        // A rejected request does not consume its key.
        $this->requestShowing('2027-03-11T15:00:00Z', [], ['Idempotency-Key' => 'showing-req-0002'])->assertStatus(409);
        $this->requestShowing('2027-03-11T16:00:00Z', [], ['Idempotency-Key' => 'showing-req-0002'])->assertCreated();
    }

    public function test_database_prevents_double_booking(): void
    {
        $base = ['agent_id' => $this->agent->id, 'contact_name' => 'A', 'timezone' => 'America/New_York'];
        Appointment::query()->create($base + ['starts_at' => '2027-03-12 15:00:00+00', 'ends_at' => '2027-03-12 15:30:00+00']);
        // Back-to-back is fine; a cancelled overlap is fine.
        Appointment::query()->create($base + ['starts_at' => '2027-03-12 15:30:00+00', 'ends_at' => '2027-03-12 16:00:00+00']);
        $cancelled = new Appointment($base + ['starts_at' => '2027-03-12 15:10:00+00', 'ends_at' => '2027-03-12 15:40:00+00']);
        $cancelled->forceFill(['status' => 'cancelled'])->save();

        $this->expectException(QueryException::class);
        Appointment::query()->create($base + ['starts_at' => '2027-03-12 15:15:00+00', 'ends_at' => '2027-03-12 15:45:00+00']);
    }

    public function test_reschedule_cancel_and_agent_bookings(): void
    {
        Notification::fake();
        $buyer = $this->actingAsRole(RoleName::Buyer);
        $id = $this->requestShowing('2027-03-11T14:00:00Z')->json('data.id');

        Sanctum::actingAs($this->agentUser);
        $this->postJson("/api/v1/appointments/{$id}/confirm")->assertOk();

        // The visitor may only move to a published slot, which needs confirming again.
        Sanctum::actingAs($buyer);
        $this->postJson("/api/v1/appointments/{$id}/reschedule", ['starts_at' => '2027-03-11T23:00:00Z'])->assertStatus(409);
        $this->postJson("/api/v1/appointments/{$id}/reschedule", ['starts_at' => '2027-03-11T16:00:00Z'])
            ->assertOk()->assertJsonPath('data.status', 'requested')->assertJsonPath('data.starts_at', '2027-03-11T16:00:00+00:00');
        Notification::assertSentTo($this->agentUser, ShowingUpdateNotification::class, fn ($n) => $n->kind === 'rescheduled');

        // The agent books a CRM lead outside the published hours, but never on top of another showing.
        Sanctum::actingAs($this->agentUser);
        $lead = Lead::query()->sole();
        $this->postJson('/api/v1/appointments/book', ['lead_id' => $lead->id, 'starts_at' => '2027-03-11T16:15:00Z'])
            ->assertStatus(409)->assertJsonPath('error.code', 'slot_unavailable');
        $this->postJson('/api/v1/appointments/book', ['lead_id' => $lead->id, 'starts_at' => '2027-03-12T00:30:00Z', 'duration_minutes' => 45])
            ->assertCreated()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.contact.name', $lead->full_name);
        $this->postJson('/api/v1/appointments/book', ['contact_name' => 'Past', 'starts_at' => '2027-03-01T00:30:00Z'])->assertUnprocessable()->assertJsonPath('error.code', 'appointment_in_past');

        Sanctum::actingAs($buyer);
        $this->postJson("/api/v1/appointments/{$id}/cancel")->assertUnprocessable();
        $this->postJson("/api/v1/appointments/{$id}/cancel", ['reason' => 'Found another home'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        Notification::assertSentTo($this->agentUser, ShowingUpdateNotification::class, fn ($n) => $n->kind === 'cancelled');
        $this->assertContains('2027-03-11T16:00:00+00:00', $this->slots('2027-03-11'));

        $types = $lead->activities()->where('type', 'showing')->get()->pluck('metadata.event')->all();
        $this->assertEqualsCanonicalizing(['confirmed', 'rescheduled', 'booked', 'cancelled'], $types);
    }

    public function test_completion_feedback_and_reminders(): void
    {
        Notification::fake();
        $buyer = $this->actingAsRole(RoleName::Buyer);
        $id = $this->requestShowing('2027-03-11T14:00:00Z')->json('data.id');
        Sanctum::actingAs($this->agentUser);
        $this->postJson("/api/v1/appointments/{$id}/confirm")->assertOk();
        $this->postJson("/api/v1/appointments/{$id}/complete", ['outcome' => 'completed'])->assertStatus(409)->assertJsonPath('error.code', 'appointment_not_started');

        // 20 h before: the 24 h reminder, once, to both sides.
        $this->travelTo(Carbon::parse('2027-03-10 18:00:00', 'UTC'));
        $this->artisan('hitlease:showings:reminders')->expectsOutputToContain('Sent 1 showing reminder(s).');
        $this->artisan('hitlease:showings:reminders')->expectsOutputToContain('Sent 0 showing reminder(s).');
        Notification::assertSentToTimes($buyer, ShowingReminderNotification::class, 1);
        Notification::assertSentTo($this->agentUser, ShowingReminderNotification::class, fn ($n) => $n->window === '24h');

        $this->travelTo(Carbon::parse('2027-03-11 13:15:00', 'UTC'));
        $this->artisan('hitlease:showings:reminders')->expectsOutputToContain('Sent 1 showing reminder(s).');
        Notification::assertSentTo($buyer, ShowingReminderNotification::class, fn ($n) => $n->window === '1h');

        $this->travelTo(Carbon::parse('2027-03-11 14:40:00', 'UTC'));
        $this->postJson("/api/v1/appointments/{$id}/complete", ['outcome' => 'completed', 'feedback_rating' => 4, 'feedback_comment' => 'Loved the kitchen'])
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.feedback.rating', 4);
        Sanctum::actingAs($buyer);
        $this->getJson("/api/v1/appointments/{$id}")->assertOk()->assertJsonPath('data.feedback', null);
        $this->getJson('/api/v1/appointments?when=past')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_agents_manage_their_hours_and_time_off(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->getJson('/api/v1/me/availability')->assertOk()->assertJsonPath('data.timezone', 'America/New_York')->assertJsonCount(7, 'data.windows');

        $this->putJson('/api/v1/me/availability', ['windows' => [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
            ['weekday' => 1, 'start_time' => '11:00', 'end_time' => '14:00'],
        ]])->assertUnprocessable();
        $this->putJson('/api/v1/me/availability', ['windows' => [['weekday' => 1, 'start_time' => '12:00', 'end_time' => '09:00']]])->assertUnprocessable();
        $this->putJson('/api/v1/me/availability', ['windows' => [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
            ['weekday' => 1, 'start_time' => '13:00', 'end_time' => '17:30'],
        ]])->assertOk()->assertJsonCount(2, 'data.windows')->assertJsonPath('data.windows.1.end_time', '17:30');

        $off = $this->postJson('/api/v1/me/time-off', ['starts_at' => '2027-03-20T14:00:00Z', 'ends_at' => '2027-03-20T22:00:00Z', 'reason' => 'Open house'])
            ->assertCreated()->json('data.id');
        $this->getJson('/api/v1/me/availability')->assertJsonPath('data.time_off.0.reason', 'Open house');
        $this->deleteJson("/api/v1/me/time-off/{$off}")->assertNoContent();

        $this->actingAsRole(RoleName::Buyer);
        $this->getJson('/api/v1/me/availability')->assertForbidden();
    }
}
