<?php

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Models\Property;
use App\Models\User;

/**
 * `update_own` means "I am the listing agent". Publication and deletion are
 * separate permissions (brokers), so agents prepare listings and brokers
 * approve them for the public site.
 */
class PropertyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PropertiesViewAny) || $actor->hasPermission(Permission::PropertiesUpdateOwn);
    }

    public function view(User $actor, Property $property): bool
    {
        return $actor->hasPermission(Permission::PropertiesViewAny) || $this->isListingAgent($actor, $property);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::PropertiesCreate);
    }

    public function update(User $actor, Property $property): bool
    {
        return $actor->hasPermission(Permission::PropertiesUpdateAny)
            || ($actor->hasPermission(Permission::PropertiesUpdateOwn) && $this->isListingAgent($actor, $property));
    }

    public function publish(User $actor, Property $property): bool
    {
        return $actor->hasPermission(Permission::PropertiesPublish);
    }

    public function delete(User $actor, Property $property): bool
    {
        return $actor->hasPermission(Permission::PropertiesDelete);
    }

    private function isListingAgent(User $actor, Property $property): bool
    {
        return $property->listing_agent_id !== null
            && $property->listingAgent()->where('user_id', $actor->id)->exists();
    }
}
