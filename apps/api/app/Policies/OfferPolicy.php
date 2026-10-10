<?php

namespace App\Policies;

use App\Application\Deals\OfferService;
use App\Domain\Identity\Enums\Permission;
use App\Models\Agent;
use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::OffersViewAny) || $actor->hasPermission(Permission::OffersManageOwn) || $actor->hasPermission(Permission::OffersManageAny);
    }

    public function view(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersViewAny) || $actor->hasPermission(Permission::OffersManageAny) || $this->involved($actor, $offer);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::OffersManageOwn) || $actor->hasPermission(Permission::OffersManageAny);
    }

    /** Edit/submit/withdraw: the side that made the offer. */
    public function author(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersManageAny)
            || ($actor->hasPermission(Permission::OffersManageOwn) && $this->isAgent($actor, app(OfferService::class)->authorAgents($offer)));
    }

    /** Accept/reject/counter: the side that must respond. */
    public function respond(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersManageAny)
            || ($actor->hasPermission(Permission::OffersManageOwn) && $this->isAgent($actor, app(OfferService::class)->responderAgents($offer)));
    }

    private function involved(User $actor, Offer $offer): bool
    {
        if (! $actor->hasPermission(Permission::OffersManageOwn)) {
            return false;
        }
        $service = app(OfferService::class);

        return $this->isAgent($actor, [...$service->authorAgents($offer), ...$service->responderAgents($offer)]);
    }

    /**
     * @param  array<int, int|null>  $agentIds
     */
    private function isAgent(User $actor, array $agentIds): bool
    {
        $ids = array_filter($agentIds);

        return $ids !== [] && Agent::query()->whereIn('id', $ids)->where('user_id', $actor->id)->exists();
    }
}
