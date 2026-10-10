<?php

namespace App\Application\Messaging;

use App\Domain\Agency\Enums\AgentStatus;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Shared\DomainException;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Private conversations. Clients (buyers/sellers) can only start conversations with active agents;
 * agents and staff with anyone active. Transaction conversations include everyone on the deal.
 */
final class MessagingService
{
    /**
     * @param  list<int>  $recipientIds
     */
    public function start(User $actor, array $recipientIds, string $body, ?string $subject, ?int $propertyId, ?Transaction $transaction): Conversation
    {
        $recipients = User::query()->whereIn('id', $recipientIds)->where('id', '!=', $actor->id)
            ->where('status', UserStatus::Active->value)->get();
        if ($recipients->isEmpty()) {
            throw new DomainException('recipient_invalid', 'Choose at least one active recipient.', 422);
        }
        // Deal conversations are authorized by the caller (the actor can view the transaction).
        if ($transaction === null && ! $this->isProfessional($actor)) {
            $agentUserIds = Agent::query()->where('status', AgentStatus::Active->value)->whereIn('user_id', $recipients->pluck('id'))->pluck('user_id');
            if ($agentUserIds->count() !== $recipients->count()) {
                throw new DomainException('recipient_not_allowed', 'You can message agents of the brokerage.', 403);
            }
        }

        $participantIds = $recipients->pluck('id')->push($actor->id)->unique()->sort()->values();

        return DB::transaction(function () use ($actor, $participantIds, $body, $subject, $propertyId, $transaction) {
            // Same people about the same thing → continue the existing conversation.
            $existing = Conversation::query()
                ->where('property_id', $propertyId)->where('transaction_id', $transaction?->id)
                ->whereHas('participants', fn ($q) => $q->whereIn('users.id', $participantIds), '=', $participantIds->count())
                ->whereDoesntHave('participants', fn ($q) => $q->whereNotIn('users.id', $participantIds))
                ->first();
            $conversation = $existing ?? Conversation::query()->create([
                'subject' => $subject,
                'property_id' => $propertyId,
                'transaction_id' => $transaction?->id,
                'created_by' => $actor->id,
            ]);
            if ($existing === null) {
                $conversation->participants()->attach($participantIds->mapWithKeys(fn ($id) => [$id => ['joined_at' => now()]])->all());
            }
            $this->send($actor, $conversation, $body);

            return $conversation;
        });
    }

    public function send(User $actor, Conversation $conversation, string $body): Message
    {
        $message = DB::transaction(function () use ($actor, $conversation, $body) {
            $message = Message::query()->create(['conversation_id' => $conversation->id, 'user_id' => $actor->id, 'body' => $body]);
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            $conversation->participants()->updateExistingPivot($actor->id, ['last_read_at' => now()]);

            return $message;
        });

        foreach ($conversation->participants()->where('users.id', '!=', $actor->id)->where('status', UserStatus::Active->value)->get() as $recipient) {
            $mail = Cache::add("message-mail:{$conversation->id}:{$recipient->id}", true, now()->addHour());
            $recipient->notify(new NewMessageNotification($message->setRelation('author', $actor), $mail));
        }

        return $message;
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    public function unreadCount(User $user, Conversation $conversation): int
    {
        $lastRead = $conversation->participants->firstWhere('id', $user->id)?->pivot->last_read_at;

        return $conversation->messages()->where('user_id', '!=', $user->id)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))->count();
    }

    /** Agents and staff (anyone who works leads or transactions). */
    public function isProfessional(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission(Permission::LeadsViewAssigned)
            || $user->hasPermission(Permission::LeadsViewAny)
            || $user->hasPermission(Permission::TransactionsViewAny);
    }

    /**
     * Users on a transaction (agents + linked clients) — used to open its conversation.
     *
     * @return list<int>
     */
    public function transactionUserIds(Transaction $transaction): array
    {
        $transaction->loadMissing(['listingAgent', 'buyerAgent', 'participants']);

        return collect([$transaction->listingAgent?->user_id, $transaction->buyerAgent?->user_id])
            ->merge($transaction->participants->pluck('user_id'))
            ->filter()->unique()->values()->all();
    }
}
