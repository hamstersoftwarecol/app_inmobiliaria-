<?php

namespace App\Application\Deals;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Deals\Enums\Contingency;
use App\Domain\Deals\Enums\MilestoneType;
use App\Domain\Deals\Enums\OfferStatus;
use App\Domain\Deals\Enums\ParticipantRole;
use App\Domain\Deals\Enums\TransactionStatus;
use App\Domain\Deals\Exceptions\DealException;
use App\Domain\Listings\Enums\PropertySource;
use App\Domain\Mls\Enums\ListingStatus;
use App\Models\Offer;
use App\Models\PropertyStatusHistory;
use App\Models\Transaction;
use App\Models\TransactionMilestone;
use App\Models\TransactionParticipant;
use App\Models\User;
use App\Notifications\Deals\DealUpdateNotification;
use App\Support\AgencyClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class TransactionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Opens the transaction for a just-accepted offer, with default New Jersey milestones. */
    public function openFromOffer(User $actor, Offer $offer): Transaction
    {
        $property = $offer->property;
        $transaction = Transaction::query()->create([
            'property_id' => $property->id,
            'offer_id' => $offer->id,
            'listing_agent_id' => $property->listing_agent_id,
            'buyer_agent_id' => $offer->buyer_agent_id,
            'sale_price' => $offer->amount,
            'contract_date' => AgencyClock::todayString(),
            'closing_date' => $offer->closing_date?->toDateString(),
            'created_by' => $actor->id,
        ]);

        TransactionParticipant::query()->create(['transaction_id' => $transaction->id, 'role' => ParticipantRole::Buyer, 'name' => $offer->buyer_name]);
        $this->seedMilestones($transaction, $offer);
        $this->setListingStatus($transaction, ListingStatus::ActiveUnderContract, $actor);
        $this->audit->log(AuditCategory::Data, 'transactions.opened', $actor, $transaction, null, ['offer_id' => $offer->id, 'sale_price' => (string) $offer->amount]);

        return $transaction;
    }

    public function close(User $actor, Transaction $transaction, ?string $closingDate): Transaction
    {
        $this->requireOpen($transaction);

        return DB::transaction(function () use ($actor, $transaction, $closingDate) {
            $closing = $closingDate ?? AgencyClock::todayString();
            $transaction->forceFill(['status' => TransactionStatus::Closed, 'closed_at' => now(), 'closing_date' => $closing])->save();
            $transaction->offer?->forceFill(['status' => OfferStatus::Closed])->save();
            $transaction->milestones()->where('type', MilestoneType::Closing->value)->whereNull('completed_at')
                ->update(['completed_at' => now(), 'completed_by' => $actor->id]);
            // Record keeping: documents are retained for the configured period after closing.
            $transaction->documents()->update(['retain_until' => Carbon::parse($closing)->addYears((int) config('hitlease.documents.retention_years', 6))->toDateString()]);
            $this->setListingStatus($transaction, ListingStatus::Closed, $actor);
            $this->audit->log(AuditCategory::Data, 'transactions.closed', $actor, $transaction, null, ['closing_date' => $closing]);
            $this->notifyAgents($transaction, $actor, 'transaction.closed', "Transaction closed: {$transaction->property?->title}");

            return $transaction;
        });
    }

    public function cancel(User $actor, Transaction $transaction, string $reason): Transaction
    {
        $this->requireOpen($transaction);

        return DB::transaction(function () use ($actor, $transaction, $reason) {
            $transaction->forceFill(['status' => TransactionStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $transaction->offer?->forceFill(['status' => OfferStatus::Terminated])->save();
            $transaction->documents()->update(['retain_until' => AgencyClock::today()->addYears((int) config('hitlease.documents.retention_years', 6))->toDateString()]);
            $this->setListingStatus($transaction, ListingStatus::Active, $actor);
            $this->audit->log(AuditCategory::Data, 'transactions.cancelled', $actor, $transaction, null, ['reason' => $reason]);
            $this->notifyAgents($transaction, $actor, 'transaction.cancelled', "Transaction cancelled: {$transaction->property?->title}");

            return $transaction;
        });
    }

    public function requireOpen(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::Open) {
            throw DealException::transactionClosed();
        }
    }

    /**
     * NJ attorney review is three business days (weekends excluded; legal holidays are not
     * modelled — agents adjust the date). Other milestones use common default periods and are
     * editable.
     */
    private function seedMilestones(Transaction $transaction, Offer $offer): void
    {
        $start = AgencyClock::today();
        $closing = $offer->closing_date ? Carbon::parse($offer->closing_date->toDateString()) : null;
        $contingencies = $offer->contingencies ?? [];
        $has = fn (Contingency $c) => in_array($c->value, $contingencies, true);
        $cap = fn (Carbon $d) => $closing && $d->gt($closing) ? $closing->copy() : $d;

        $rows = [[MilestoneType::AttorneyReview, $this->addBusinessDays($start, 3)]];
        if ($has(Contingency::Inspection)) {
            $rows[] = [MilestoneType::Inspection, $cap($start->copy()->addDays(10))];
        }
        if ($has(Contingency::Appraisal)) {
            $rows[] = [MilestoneType::Appraisal, $cap($start->copy()->addDays(21))];
        }
        if ($has(Contingency::Financing)) {
            $rows[] = [MilestoneType::MortgageCommitment, $cap($start->copy()->addDays(30))];
        }
        $rows[] = [MilestoneType::TitleSearch, $cap($start->copy()->addDays(30))];
        if ($closing) {
            $rows[] = [MilestoneType::FinalWalkthrough, $closing->copy()->subDay()];
        }
        $rows[] = [MilestoneType::Closing, $closing];

        foreach ($rows as $i => [$type, $due]) {
            TransactionMilestone::query()->create([
                'transaction_id' => $transaction->id,
                'type' => $type,
                'title' => $type->title(),
                'due_date' => $due?->toDateString(),
                'sort_order' => $i,
            ]);
        }
    }

    public function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $d = $date->copy();
        while ($days > 0) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $days--;
            }
        }

        return $d;
    }

    private function setListingStatus(Transaction $transaction, ListingStatus $status, User $actor): void
    {
        $property = $transaction->property;
        if ($property === null || $property->source !== PropertySource::Internal || $property->status === $status) {
            return;
        }
        $from = $property->status;
        $property->forceFill(['status' => $status])->save();
        PropertyStatusHistory::query()->create([
            'property_id' => $property->id, 'from_status' => $from->value, 'to_status' => $status->value,
            'from_price' => $property->list_price, 'to_price' => $property->list_price, 'source' => 'deal', 'changed_by' => $actor->id,
        ]);
    }

    public function notifyAgents(Transaction $transaction, User $actor, string $type, string $summary): void
    {
        $transaction->loadMissing(['listingAgent.user', 'buyerAgent.user']);
        foreach ([$transaction->listingAgent?->user, $transaction->buyerAgent?->user] as $user) {
            if ($user !== null && $user->id !== $actor->id) {
                $user->notify(new DealUpdateNotification($type, $summary, "/dashboard/transactions/{$transaction->id}", ['transaction_id' => $transaction->id]));
            }
        }
    }
}
