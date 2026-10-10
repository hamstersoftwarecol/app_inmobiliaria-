<?php

namespace App\Console\Commands;

use App\Domain\Deals\Enums\TransactionStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\TransactionMilestone;
use App\Models\User;
use App\Notifications\Deals\DealUpdateNotification;
use App\Support\AgencyClock;
use Illuminate\Console\Command;

class SendMilestoneReminders extends Command
{
    protected $signature = 'hitlease:transactions:milestone-reminders {--days=2 : Remind this many agency days before the due date}';

    protected $description = 'Remind the agents on open transactions about contract milestones that are coming due (once per due date)';

    public function handle(): int
    {
        $sent = 0;
        $horizon = AgencyClock::today()->addDays((int) $this->option('days'))->toDateString();

        TransactionMilestone::query()
            ->whereNull('completed_at')->whereNull('reminder_sent_at')
            ->whereNotNull('due_date')->where('due_date', '<=', $horizon)
            ->whereHas('transaction', fn ($q) => $q->where('status', TransactionStatus::Open->value))
            ->with(['transaction.listingAgent.user', 'transaction.buyerAgent.user', 'transaction.property'])
            ->chunkById(200, function ($milestones) use (&$sent) {
                foreach ($milestones as $milestone) {
                    $claimed = TransactionMilestone::query()->whereKey($milestone->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                    if ($claimed !== 1) {
                        continue;
                    }
                    $transaction = $milestone->transaction;
                    $address = $transaction->property?->street_address ?? $transaction->property?->title ?? "#{$transaction->id}";
                    $summary = "Milestone due {$milestone->due_date->toDateString()}: {$milestone->title} ({$address})";
                    collect([$transaction->listingAgent?->user, $transaction->buyerAgent?->user])
                        ->filter(fn (?User $u) => $u !== null && $u->status === UserStatus::Active)
                        ->unique('id')
                        ->each(function (User $u) use ($milestone, $transaction, $summary, &$sent) {
                            $u->notify(new DealUpdateNotification('transaction.milestone_due', $summary, "/dashboard/transactions/{$transaction->id}", [
                                'transaction_id' => $transaction->id, 'milestone_id' => $milestone->id,
                            ]));
                            $sent++;
                        });
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
