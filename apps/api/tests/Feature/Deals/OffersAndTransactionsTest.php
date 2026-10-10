<?php

namespace Tests\Feature\Deals;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Deals\DealUpdateNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OffersAndTransactionsTest extends TestCase
{
    private Agent $listingAgent;

    private Agent $buyerAgent;

    private Property $listing;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday, June 2, 2027 10:00 in New Jersey.
        $this->travelTo(Carbon::parse('2027-06-02 14:00:00', 'UTC'));
        $this->listingAgent = Agent::factory()->create();
        $this->buyerAgent = Agent::factory()->create();
        $this->listing = Property::factory()->forAgent($this->listingAgent)->published()->create(['title' => 'Offer colonial', 'list_price' => 650000]);
    }

    private function as(Agent $agent): User
    {
        $user = User::find($agent->user_id)->fresh();
        Sanctum::actingAs($user);

        return $user;
    }

    private function offer(array $overrides = []): array
    {
        return array_merge([
            'property_id' => $this->listing->id, 'buyer_name' => 'Jordan Buyer', 'amount' => 630000, 'earnest_money' => 20000,
            'financing' => 'conventional', 'down_payment_percent' => 20, 'closing_date' => '2027-08-15',
            'contingencies' => ['inspection', 'financing', 'appraisal'], 'submit' => true,
        ], $overrides);
    }

    public function test_offer_counter_and_acceptance_open_a_transaction_with_nj_milestones(): void
    {
        Notification::fake();
        $this->as($this->buyerAgent);
        $offerId = $this->postJson('/api/v1/offers', $this->offer())->assertCreated()
            ->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.side', 'buyer')->json('data.id');
        Notification::assertSentTo(User::find($this->listingAgent->user_id), DealUpdateNotification::class, fn ($n) => $n->type === 'offer.submitted');

        // The buyer's agent cannot accept their own offer.
        $this->postJson("/api/v1/offers/{$offerId}/accept")->assertForbidden();

        $this->as($this->listingAgent);
        $counterId = $this->postJson("/api/v1/offers/{$offerId}/counter", ['amount' => 645000, 'note' => 'Seller will meet partway.'])
            ->assertCreated()->assertJsonPath('data.side', 'seller')->assertJsonPath('data.status', 'submitted')->json('data.id');
        $this->assertSame('countered', Offer::find($offerId)->status->value);
        // Only the latest offer of a negotiation is listed.
        $this->assertSame([$counterId], array_column($this->getJson('/api/v1/offers')->assertOk()->json('data'), 'id'));
        $this->assertCount(2, $this->getJson("/api/v1/offers/{$offerId}")->assertOk()->json('data'));

        // Now the buyer's side responds.
        $this->postJson("/api/v1/offers/{$counterId}/accept")->assertForbidden();
        $this->as($this->buyerAgent);
        $tx = $this->postJson("/api/v1/offers/{$counterId}/accept")->assertCreated()
            ->assertJsonPath('data.status', 'open')->assertJsonPath('data.sale_price', 645000)
            ->assertJsonPath('data.closing_date', '2027-08-15')->json('data');

        $this->assertSame('active_under_contract', $this->listing->fresh()->status->value);
        $milestones = collect($tx['milestones']);
        $this->assertSame(['attorney_review', 'inspection', 'appraisal', 'mortgage_commitment', 'title_search', 'final_walkthrough', 'closing'], $milestones->pluck('type')->all());
        // Three business days from Wednesday → Monday.
        $this->assertSame('2027-06-07', $milestones->firstWhere('type', 'attorney_review')['due_date']);
        $this->assertSame('2027-08-14', $milestones->firstWhere('type', 'final_walkthrough')['due_date']);
        $this->assertTrue(AuditLog::query()->where('event', 'transactions.opened')->exists());

        // A second accepted offer on the same property is refused.
        $second = $this->postJson('/api/v1/offers', $this->offer(['amount' => 660000]))->assertCreated()->json('data.id');
        $this->as($this->listingAgent);
        $this->postJson("/api/v1/offers/{$second}/accept")->assertStatus(409)->assertJsonPath('error.code', 'property_under_contract');
    }

    public function test_drafts_are_editable_until_submitted_and_can_be_withdrawn(): void
    {
        Notification::fake();
        $this->as($this->buyerAgent);
        $id = $this->postJson('/api/v1/offers', $this->offer(['submit' => false]))->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        Notification::assertNothingSent();
        $bySlug = $this->postJson('/api/v1/offers', $this->offer(['property_id' => null, 'property_slug' => $this->listing->slug, 'submit' => false]))->assertCreated();
        $this->assertSame($this->listing->id, $bySlug->json('data.property.id'));

        $this->patchJson("/api/v1/offers/{$id}", ['amount' => 640000])->assertOk()->assertJsonPath('data.amount', 640000);
        $this->postJson("/api/v1/offers/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->patchJson("/api/v1/offers/{$id}", ['amount' => 1])->assertStatus(409)->assertJsonPath('error.code', 'offer_invalid_transition');
        $this->postJson("/api/v1/offers/{$id}/withdraw", ['note' => 'Buyer changed plans'])->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->postJson("/api/v1/offers/{$id}/withdraw")->assertStatus(409);
    }

    public function test_validation_and_authorization(): void
    {
        $this->as($this->buyerAgent);
        $this->postJson('/api/v1/offers', $this->offer(['amount' => 0, 'financing' => 'barter', 'contingencies' => ['astrology'], 'closing_date' => '2020-01-01']))
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['fields' => ['amount', 'financing', 'contingencies.0', 'closing_date']]]]);

        $id = $this->postJson('/api/v1/offers', $this->offer())->assertCreated()->json('data.id');

        // An unrelated agent sees nothing.
        $this->as(Agent::factory()->create());
        $this->getJson("/api/v1/offers/{$id}")->assertForbidden();
        $this->getJson('/api/v1/offers')->assertOk()->assertJsonCount(0, 'data');

        // Buyers do not record offers through the API (their agent does).
        $this->actingAsRole(RoleName::Buyer);
        $this->postJson('/api/v1/offers', $this->offer())->assertForbidden();
        $this->getJson('/api/v1/offers')->assertForbidden();

        // Brokers see everything.
        $this->actingAsRole(RoleName::Broker);
        $this->getJson("/api/v1/offers/{$id}")->assertOk();
        $this->getJson('/api/v1/offers')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_offers_past_their_deadline_expire(): void
    {
        Notification::fake();
        $this->as($this->buyerAgent);
        $id = $this->postJson('/api/v1/offers', $this->offer(['expires_at' => '2027-06-03T21:00:00Z']))->assertCreated()->json('data.id');

        $this->artisan('hitlease:offers:expire')->assertSuccessful();
        $this->assertSame('submitted', Offer::find($id)->status->value);

        $this->travelTo(Carbon::parse('2027-06-03 21:01:00', 'UTC'));
        $this->artisan('hitlease:offers:expire')->expectsOutput('Expired 1 offer(s).')->assertSuccessful();
        $this->assertSame('expired', Offer::find($id)->status->value);
        $this->as($this->listingAgent);
        $this->postJson("/api/v1/offers/{$id}/accept")->assertStatus(409);
    }

    private function openTransaction(): Transaction
    {
        Notification::fake();
        $this->as($this->buyerAgent);
        $id = $this->postJson('/api/v1/offers', $this->offer())->json('data.id');
        $this->as($this->listingAgent);
        $txId = $this->postJson("/api/v1/offers/{$id}/accept")->assertCreated()->json('data.id');

        return Transaction::findOrFail($txId);
    }

    public function test_transaction_participants_milestones_and_client_access(): void
    {
        $tx = $this->openTransaction();
        $client = $this->userWithRole(RoleName::Buyer, ['email' => 'jordan.buyer@example.com']);

        // Adding a participant links an existing account by e-mail.
        $this->postJson("/api/v1/transactions/{$tx->id}/participants", ['role' => 'buyer', 'name' => 'Jordan Buyer', 'email' => 'Jordan.Buyer@example.com', 'phone' => '201-555-0100'])
            ->assertOk();
        $linked = $tx->participants()->where('user_id', $client->id)->sole();
        $this->assertSame('Jordan.Buyer@example.com', $linked->email);

        $milestone = $this->postJson("/api/v1/transactions/{$tx->id}/milestones", ['title' => 'HOA documents', 'due_date' => '2027-06-20'])
            ->assertOk()->json('data.milestones');
        $custom = collect($milestone)->firstWhere('title', 'HOA documents');
        $this->patchJson("/api/v1/transactions/{$tx->id}/milestones/{$custom['id']}", ['completed' => true])->assertOk();
        $this->assertNotNull($tx->milestones()->find($custom['id'])->completed_at);
        $standard = collect($milestone)->firstWhere('type', 'attorney_review');
        $this->deleteJson("/api/v1/transactions/{$tx->id}/milestones/{$standard['id']}")->assertNotFound();
        $this->deleteJson("/api/v1/transactions/{$tx->id}/milestones/{$custom['id']}")->assertNoContent();

        // The client sees the deal (without other participants' contact details) but cannot change it.
        Sanctum::actingAs($client);
        $this->getJson('/api/v1/transactions')->assertOk()->assertJsonCount(1, 'data');
        $seen = $this->getJson("/api/v1/transactions/{$tx->id}")->assertOk()->assertJsonPath('data.can.manage', false)->json('data.participants');
        $this->assertSame([null, null], array_column($seen, 'email'));
        $this->patchJson("/api/v1/transactions/{$tx->id}", ['closing_date' => '2027-08-30'])->assertForbidden();

        // Others see nothing.
        $this->actingAsRole(RoleName::Buyer);
        $this->getJson('/api/v1/transactions')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/transactions/{$tx->id}")->assertForbidden();
    }

    public function test_closing_and_cancelling(): void
    {
        $tx = $this->openTransaction();
        $this->postJson("/api/v1/transactions/{$tx->id}/close", ['closing_date' => '2027-06-10'])->assertStatus(422);
        $this->postJson("/api/v1/transactions/{$tx->id}/close", ['closing_date' => '2027-06-02'])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertSame('closed', $this->listing->fresh()->status->value);
        $this->assertSame('closed', $tx->offer->fresh()->status->value);
        $this->postJson("/api/v1/transactions/{$tx->id}/cancel", ['reason' => 'Too late'])->assertStatus(409)->assertJsonPath('error.code', 'transaction_not_open');
        $this->postJson('/api/v1/offers', $this->offer())->assertStatus(422)->assertJsonPath('error.code', 'property_not_offerable');
    }

    public function test_cancelling_returns_the_listing_to_active_and_allows_new_offers(): void
    {
        $tx = $this->openTransaction();
        $this->postJson("/api/v1/transactions/{$tx->id}/cancel")->assertStatus(422);
        $this->postJson("/api/v1/transactions/{$tx->id}/cancel", ['reason' => 'Financing fell through'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('active', $this->listing->fresh()->status->value);
        $this->assertSame('terminated', $tx->offer->fresh()->status->value);

        $this->as($this->buyerAgent);
        $id = $this->postJson('/api/v1/offers', $this->offer())->assertCreated()->json('data.id');
        $this->as($this->listingAgent);
        $this->postJson("/api/v1/offers/{$id}/accept")->assertCreated();
    }

    public function test_milestone_reminders_go_to_the_agents_once(): void
    {
        $tx = $this->openTransaction();
        Notification::fake();
        // Attorney review is due Monday June 7; reminders start two agency days before.
        $this->artisan('hitlease:transactions:milestone-reminders')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo(Carbon::parse('2027-06-05 12:00:00', 'UTC'));
        $this->artisan('hitlease:transactions:milestone-reminders')->expectsOutput('Sent 2 reminder(s).')->assertSuccessful();
        Notification::assertSentTo(User::find($this->listingAgent->user_id), DealUpdateNotification::class, fn ($n) => $n->type === 'transaction.milestone_due' && $n->path === "/dashboard/transactions/{$tx->id}");
        $this->artisan('hitlease:transactions:milestone-reminders')->expectsOutput('Sent 0 reminder(s).');
    }
}
