<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $actor): bool
    {
        return true; // the list is scoped by Transaction::visibleTo
    }

    public function view(User $actor, Transaction $transaction): bool
    {
        return $actor->hasPermission(Permission::TransactionsViewAny)
            || $transaction->isAgentOn($actor)
            || $transaction->isClient($actor);
    }

    public function manage(User $actor, Transaction $transaction): bool
    {
        return $actor->hasPermission(Permission::TransactionsManageAny)
            || ($actor->hasPermission(Permission::TransactionsManageOwn) && $transaction->isAgentOn($actor));
    }
}
