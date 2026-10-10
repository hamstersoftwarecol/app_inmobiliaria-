<?php

namespace Tests\Feature\Messaging;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    public function test_a_buyer_messages_an_agent_and_they_converse(): void
    {
        Notification::fake();
        $agent = Agent::factory()->create();
        $agentUser = User::find($agent->user_id)->fresh();
        $listing = Property::factory()->forAgent($agent)->published()->create();
        $buyer = $this->actingAsRole(RoleName::Buyer);

        $id = $this->postJson('/api/v1/conversations', ['recipient_ids' => [$agentUser->id], 'property_id' => $listing->id, 'body' => 'Is the basement finished?'])
            ->assertCreated()->assertJsonPath('data.messages.0.body', 'Is the basement finished?')->assertJsonPath('data.messages.0.mine', true)->json('data.id');
        Notification::assertSentTo($agentUser, NewMessageNotification::class, fn ($n, $channels) => in_array('mail', $channels, true));

        // Asking again about the same listing continues the same conversation.
        $this->postJson('/api/v1/conversations', ['recipient_ids' => [$agentUser->id], 'property_id' => $listing->id, 'body' => 'Also, HOA fees?'])
            ->assertCreated()->assertJsonPath('data.id', $id)->assertJsonCount(2, 'data.messages');

        Sanctum::actingAs($agentUser);
        $this->getJson('/api/v1/conversations/unread-count')->assertOk()->assertJsonPath('data.count', 2);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread', 2)->assertJsonPath('data.0.participants.0.id', $buyer->id);
        $this->getJson("/api/v1/conversations/{$id}")->assertOk()->assertJsonPath('data.messages.1.mine', false);
        $this->getJson('/api/v1/conversations/unread-count')->assertJsonPath('data.count', 0);
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'Yes, finished in 2021.'])->assertCreated()->assertJsonPath('data.mine', true);

        // Mail for the same conversation is throttled to one per hour; in-app always.
        Notification::assertSentTo($buyer, NewMessageNotification::class);
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'And HOA is $200/month.'])->assertCreated();
        Notification::assertSentToTimes($buyer, NewMessageNotification::class, 2);
        $sent = Notification::sent($buyer, NewMessageNotification::class);
        $this->assertSame([true, false], $sent->map(fn ($n) => in_array('mail', $n->via($buyer), true))->all());

        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => ''])->assertStatus(422);
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => str_repeat('x', 5001)])->assertStatus(422);
    }

    public function test_conversations_are_private_even_from_administrators(): void
    {
        Notification::fake();
        $agentUser = User::find(Agent::factory()->create()->user_id)->fresh();
        $this->actingAsRole(RoleName::Buyer);
        $id = $this->postJson('/api/v1/conversations', ['recipient_ids' => [$agentUser->id], 'body' => 'Hello'])->assertCreated()->json('data.id');

        $this->actingAsRole(RoleName::Broker);
        $this->getJson("/api/v1/conversations/{$id}")->assertNotFound();
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'Hi'])->assertNotFound();
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsRole(RoleName::SuperAdmin);
        $this->getJson("/api/v1/conversations/{$id}")->assertNotFound();
    }

    public function test_clients_can_only_start_conversations_with_active_agents(): void
    {
        $otherBuyer = $this->userWithRole(RoleName::Buyer);
        $this->actingAsRole(RoleName::Buyer);
        $this->postJson('/api/v1/conversations', ['recipient_ids' => [$otherBuyer->id], 'body' => 'Hi'])->assertForbidden()->assertJsonPath('error.code', 'recipient_not_allowed');
        $this->postJson('/api/v1/conversations', ['recipient_ids' => [999999], 'body' => 'Hi'])->assertStatus(422)->assertJsonPath('error.code', 'recipient_invalid');
        $this->postJson('/api/v1/conversations', ['body' => 'Hi'])->assertStatus(422);

        $agent = Agent::factory()->create();
        $contacts = $this->getJson('/api/v1/conversations/contacts')->assertOk()->json('data');
        $this->assertContains($agent->user_id, array_column($contacts, 'id'));
        $this->assertNotContains($otherBuyer->id, array_column($contacts, 'id'));
    }

    public function test_transaction_conversations_include_everyone_on_the_deal(): void
    {
        Notification::fake();
        $listingAgent = Agent::factory()->create();
        $buyerAgent = Agent::factory()->create();
        $property = Property::factory()->forAgent($listingAgent)->published()->create();
        $offer = Offer::query()->create(['property_id' => $property->id, 'buyer_agent_id' => $buyerAgent->id, 'buyer_name' => 'J', 'amount' => 1, 'financing' => 'cash', 'side' => 'buyer', 'created_by' => $listingAgent->user_id]);
        $tx = Transaction::query()->create(['property_id' => $property->id, 'offer_id' => $offer->id, 'listing_agent_id' => $listingAgent->id, 'buyer_agent_id' => $buyerAgent->id, 'sale_price' => 1, 'contract_date' => '2027-01-01', 'created_by' => $listingAgent->user_id]);
        $seller = $this->userWithRole(RoleName::Seller);
        $tx->participants()->create(['role' => 'seller', 'name' => 'S', 'user_id' => $seller->id]);

        Sanctum::actingAs($seller);
        $data = $this->postJson('/api/v1/conversations', ['transaction_id' => $tx->id, 'body' => 'When is the inspection?'])->assertCreated()->json('data');
        $this->assertEqualsCanonicalizing([$listingAgent->user_id, $buyerAgent->user_id], array_column($data['participants'], 'id'));

        $this->actingAsRole(RoleName::Buyer);
        $this->postJson('/api/v1/conversations', ['transaction_id' => $tx->id, 'body' => 'Let me in'])->assertForbidden();
    }

    public function test_messages_are_immutable(): void
    {
        Notification::fake();
        $agentUser = User::find(Agent::factory()->create()->user_id)->fresh();
        $this->actingAsRole(RoleName::Buyer);
        $this->postJson('/api/v1/conversations', ['recipient_ids' => [$agentUser->id], 'body' => 'Original'])->assertCreated();

        $this->expectException(LogicException::class);
        Message::query()->sole()->update(['body' => 'Edited']);
    }

    public function test_users_without_messaging_permission_are_refused(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/conversations')->assertForbidden();
        $this->assertSame(0, Conversation::query()->count());
    }
}
