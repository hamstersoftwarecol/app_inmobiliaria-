<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;

/**
 * Staff with `documents.view_any` and the agents on a deal see every document; clients on the deal
 * (with `documents.view_shared`) only those explicitly shared with them.
 */
class DocumentPolicy
{
    public function viewAnyFor(User $actor, Transaction $transaction): bool
    {
        return $this->seesInternal($actor, $transaction) || $this->isSharedClient($actor, $transaction);
    }

    public function view(User $actor, Document $document): bool
    {
        $transaction = $document->transaction;

        return $this->seesInternal($actor, $transaction)
            || ($document->isShared() && $this->isSharedClient($actor, $transaction));
    }

    public function createFor(User $actor, Transaction $transaction): bool
    {
        return $actor->hasPermission(Permission::DocumentsManageAny)
            || ($actor->hasPermission(Permission::DocumentsManageOwn) && $transaction->isAgentOn($actor));
    }

    public function manage(User $actor, Document $document): bool
    {
        return $this->createFor($actor, $document->transaction);
    }

    public function seesInternal(User $actor, Transaction $transaction): bool
    {
        return $actor->hasPermission(Permission::DocumentsViewAny)
            || $actor->hasPermission(Permission::DocumentsManageAny)
            || ($actor->hasPermission(Permission::DocumentsManageOwn) && $transaction->isAgentOn($actor));
    }

    private function isSharedClient(User $actor, Transaction $transaction): bool
    {
        return $actor->hasPermission(Permission::DocumentsViewShared) && $transaction->isClient($actor);
    }
}
