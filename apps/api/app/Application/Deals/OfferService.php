<?php

namespace App\Application\Deals;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Deals\Enums\OfferStatus;
use App\Domain\Deals\Enums\TransactionStatus;
use App\Domain\Deals\Exceptions\DealException;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Mls\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\Deals\DealUpdateNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Offer state machine: draft → submitted → (countered | accepted | rejected | withdrawn | expired).
 * A counter is a new offer in the chain made by the other side. Every transition is audited.
 *
 * "Seller side" responses are made by the listing agent; for listings without an agent of ours
 * (e.g. MLS listings) the buyer's agent records the outside party's responses.
 */
final class OfferService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TransactionService $transactions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Property $property, array $data, bool $submit): Offer
    {
        if ($property->status === ListingStatus::Closed) {
            throw DealException::notOfferable();
        }
        $manageAny = $actor->hasPermission(Permission::OffersManageAny);
        // Agents always act as the buyer's agent themselves; managers may record it for any agent
        // (or none, when the buyer is represented outside the brokerage).
        $buyerAgentId = match (true) {
            $manageAny && isset($data['buyer_agent_id']) => Agent::query()->where('status', 'active')->findOrFail($data['buyer_agent_id'])->id,
            $actor->agentProfile !== null => $actor->agentProfile->id,
            $manageAny => null,
            default => throw DealException::noAgentProfile(),
        };

        return DB::transaction(function () use ($actor, $property, $data, $submit, $buyerAgentId) {
            $offer = Offer::query()->create([
                ...collect($data)->only(['lead_id', 'buyer_agent_external', 'buyer_name', 'amount', 'earnest_money', 'financing', 'down_payment_percent', 'closing_date', 'contingencies', 'terms', 'expires_at'])->all(),
                'property_id' => $property->id,
                'buyer_agent_id' => $buyerAgentId,
                'side' => 'buyer',
                'created_by' => $actor->id,
            ]);
            $this->audit->log(AuditCategory::Data, 'offers.created', $actor, $offer, null, ['amount' => (string) $offer->amount, 'property_id' => $property->id]);
            if ($submit) {
                $this->submit($actor, $offer);
            }

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(User $actor, Offer $offer, array $data): Offer
    {
        $this->requireStatus($offer, OfferStatus::Draft, 'edited');
        $offer->fill(collect($data)->only(['buyer_name', 'buyer_agent_external', 'amount', 'earnest_money', 'financing', 'down_payment_percent', 'closing_date', 'contingencies', 'terms', 'expires_at'])->all())->save();

        return $offer;
    }

    public function submit(User $actor, Offer $offer): Offer
    {
        $this->requireStatus($offer, OfferStatus::Draft, 'submitted');
        $this->transition($actor, $offer, OfferStatus::Submitted, null, ['submitted_at' => now()]);
        $this->notifyResponder($offer, $actor, 'offer.submitted', 'New offer on '.$offer->property?->title);

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    public function counter(User $actor, Offer $offer, array $terms): Offer
    {
        $this->requireLive($offer, 'countered');

        return DB::transaction(function () use ($actor, $offer, $terms) {
            $this->transition($actor, $offer, OfferStatus::Countered, $terms['note'] ?? null, ['responded_at' => now()]);
            $counter = $offer->replicate(['status', 'submitted_at', 'responded_at', 'response_note', 'status_changed_by', 'created_at', 'updated_at']);
            $counter->fill([
                ...collect($terms)->only(['amount', 'earnest_money', 'financing', 'down_payment_percent', 'closing_date', 'contingencies', 'terms', 'expires_at'])->all(),
                'parent_id' => $offer->id,
                'side' => $offer->side === 'buyer' ? 'seller' : 'buyer',
                'created_by' => $actor->id,
            ]);
            $counter->forceFill(['status' => OfferStatus::Submitted, 'submitted_at' => now()])->save();
            $this->audit->log(AuditCategory::Data, 'offers.countered', $actor, $counter, ['offer_id' => $offer->id, 'amount' => (string) $offer->amount], ['amount' => (string) $counter->amount]);
            $this->notifyResponder($counter, $actor, 'offer.countered', 'Counter-offer on '.$offer->property?->title);

            return $counter;
        });
    }

    /** Accepting opens the transaction; a property can only have one accepted offer at a time. */
    public function accept(User $actor, Offer $offer): Transaction
    {
        $this->requireLive($offer, 'accepted');
        if (Transaction::query()->where('property_id', $offer->property_id)->where('status', TransactionStatus::Open->value)->exists()) {
            throw DealException::propertyUnderContract();
        }

        try {
            return DB::transaction(function () use ($actor, $offer) {
                $this->transition($actor, $offer, OfferStatus::Accepted, null, ['responded_at' => now()]);
                $transaction = $this->transactions->openFromOffer($actor, $offer);
                $this->notifyResponder($offer, $actor, 'offer.accepted', 'Offer accepted: '.$offer->property?->title, toAuthorSide: true);

                return $transaction;
            });
        } catch (UniqueConstraintViolationException) {
            throw DealException::propertyUnderContract();
        }
    }

    public function reject(User $actor, Offer $offer, ?string $note): Offer
    {
        $this->requireLive($offer, 'rejected');
        $this->transition($actor, $offer, OfferStatus::Rejected, $note, ['responded_at' => now()]);
        $this->notifyResponder($offer, $actor, 'offer.rejected', 'Offer rejected: '.$offer->property?->title, toAuthorSide: true);

        return $offer;
    }

    public function withdraw(User $actor, Offer $offer, ?string $note): Offer
    {
        if (! in_array($offer->status, [OfferStatus::Draft, OfferStatus::Submitted], true)) {
            throw DealException::invalidTransition($offer->status->value, 'withdrawn');
        }
        $this->transition($actor, $offer, OfferStatus::Withdrawn, $note);
        if ($offer->submitted_at !== null) {
            $this->notifyResponder($offer, $actor, 'offer.withdrawn', 'Offer withdrawn: '.$offer->property?->title);
        }

        return $offer;
    }

    /** Scheduled: submitted offers past their deadline. */
    public function expireDue(): int
    {
        $count = 0;
        Offer::query()->where('status', OfferStatus::Submitted->value)->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->each(function (Offer $offer) use (&$count) {
                $offer->forceFill(['status' => OfferStatus::Expired, 'responded_at' => now()])->save();
                $this->audit->log(AuditCategory::Data, 'offers.expired', null, $offer);
                $count++;
            });

        return $count;
    }

    /** Agent(s) acting for the side that must respond to this offer. */
    public function responderAgents(Offer $offer): array
    {
        $listingAgent = $offer->property?->listing_agent_id;
        if ($offer->side === 'buyer') {
            return array_filter([$listingAgent ?? $offer->buyer_agent_id]);
        }

        return array_filter([$offer->buyer_agent_id]);
    }

    /** Agent(s) on the side that made this offer. */
    public function authorAgents(Offer $offer): array
    {
        $listingAgent = $offer->property?->listing_agent_id;

        return $offer->side === 'buyer' ? array_filter([$offer->buyer_agent_id]) : array_filter([$listingAgent ?? $offer->buyer_agent_id]);
    }

    private function requireStatus(Offer $offer, OfferStatus $status, string $action): void
    {
        if ($offer->status !== $status) {
            throw DealException::invalidTransition($offer->status->value, $action);
        }
    }

    private function requireLive(Offer $offer, string $action): void
    {
        $this->requireStatus($offer, OfferStatus::Submitted, $action);
        if ($offer->expires_at !== null && $offer->expires_at->isPast()) {
            throw DealException::offerExpired();
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(User $actor, Offer $offer, OfferStatus $to, ?string $note, array $extra = []): void
    {
        $from = $offer->status;
        $offer->forceFill([...$extra, 'status' => $to, 'response_note' => $note ?? $offer->response_note, 'status_changed_by' => $actor->id])->save();
        $this->audit->log(AuditCategory::Data, "offers.{$to->value}", $actor, $offer, ['status' => $from->value], ['status' => $to->value]);
    }

    private function notifyResponder(Offer $offer, User $actor, string $type, string $summary, bool $toAuthorSide = false): void
    {
        $agentIds = $toAuthorSide ? $this->authorAgents($offer) : $this->responderAgents($offer);
        Agent::query()->whereIn('id', $agentIds)->with('user')->get()
            ->map->user->filter(fn (?User $u) => $u !== null && $u->id !== $actor->id)
            ->each(fn (User $u) => $u->notify(new DealUpdateNotification($type, $summary, "/dashboard/offers/{$offer->root()->id}", ['offer_id' => $offer->id])));
    }
}
