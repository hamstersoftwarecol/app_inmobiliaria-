<?php

namespace App\Application\Listings;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Listings\Enums\PropertySource;
use App\Domain\Listings\Exceptions\ListingException;
use App\Models\Agent;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Models\User;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Agency-owned (internal) listings. MLS listings are maintained only by the
 * sync pipeline. Agents without `properties.update_any` always list under
 * their own agent profile; publication is a separate, permissioned step.
 */
class PropertyService
{
    /** Fields required before a listing can be published. */
    private const PUBLISH_REQUIREMENTS = ['title', 'property_type_id', 'list_price', 'street_address', 'city', 'postal_code', 'listing_agent_id'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Property
    {
        $agent = $this->resolveListingAgent($actor, $data['listing_agent_id'] ?? null);

        return DB::transaction(function () use ($actor, $data, $agent) {
            $property = new Property($data);
            $property->forceFill([
                'source' => PropertySource::Internal,
                'listing_agent_id' => $agent?->id,
                'agency_id' => $agent?->agency_id ?? $actor->agentProfile?->agency_id,
                'created_by' => $actor->id,
                'is_published' => false,
            ]);
            $property->slug = UniqueSlug::for(implode(' ', array_filter([$data['street_address'] ?? $data['title'], $data['city'] ?? null, 'nj'])), Property::query());
            $property->save();

            $this->recordHistory($property, null, null, $actor);
            $this->audit->log(AuditCategory::Data, 'properties.created', $actor, $property, new: $property->only(['title', 'status', 'list_price', 'listing_agent_id']));

            return $property;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Property $property, array $data): Property
    {
        if ($property->isMls()) {
            throw ListingException::mlsReadOnly();
        }
        if (array_key_exists('listing_agent_id', $data)) {
            $data['listing_agent_id'] = $this->resolveListingAgent($actor, $data['listing_agent_id'], $property)?->id;
        }

        return DB::transaction(function () use ($actor, $property, $data) {
            $previousStatus = $property->status;
            $previousPrice = $property->list_price;

            $property->fill($data);
            $changes = $property->getDirty();
            $original = array_intersect_key($property->getOriginal(), $changes);
            $property->save();

            if ($previousStatus !== $property->status || (string) $previousPrice !== (string) $property->list_price) {
                $this->recordHistory($property, $previousStatus?->value, $previousPrice, $actor);
            }
            // A published listing that is no longer publishable is taken down.
            if ($property->is_published && $this->missingForPublication($property) !== []) {
                $property->forceFill(['is_published' => false])->save();
                $changes['is_published'] = false;
            }
            if ($changes !== []) {
                $this->audit->log(AuditCategory::Data, 'properties.updated', $actor, $property, $this->plain($original), $this->plain($changes));
            }

            return $property;
        });
    }

    public function publish(User $actor, Property $property): Property
    {
        if ($property->isMls()) {
            throw ListingException::mlsPublicationManaged();
        }
        $missing = $this->missingForPublication($property);
        if ($missing !== []) {
            throw ListingException::notPublishable($missing);
        }

        if (! $property->is_published) {
            $property->forceFill(['is_published' => true, 'published_at' => $property->published_at ?? now()])->save();
            $this->audit->log(AuditCategory::Data, 'properties.published', $actor, $property);
        }

        return $property;
    }

    public function unpublish(User $actor, Property $property): Property
    {
        if ($property->isMls()) {
            throw ListingException::mlsPublicationManaged();
        }
        if ($property->is_published) {
            $property->forceFill(['is_published' => false])->save();
            $this->audit->log(AuditCategory::Data, 'properties.unpublished', $actor, $property);
        }

        return $property;
    }

    public function delete(User $actor, Property $property): void
    {
        if ($property->isMls()) {
            throw ListingException::mlsReadOnly();
        }
        $property->forceFill(['is_published' => false])->save();
        $property->delete();
        $this->audit->log(AuditCategory::Data, 'properties.deleted', $actor, $property);
    }

    /**
     * @return list<string>
     */
    public function missingForPublication(Property $property): array
    {
        return array_values(array_filter(self::PUBLISH_REQUIREMENTS, fn (string $f) => blank($property->{$f})));
    }

    private function resolveListingAgent(User $actor, mixed $agentId, ?Property $property = null): ?Agent
    {
        $canAssignAny = $actor->isSuperAdmin() || $actor->hasPermission(Permission::PropertiesUpdateAny);

        if (! $canAssignAny) {
            // Agents always list under their own profile.
            $own = $actor->agentProfile;
            if ($own === null || ($agentId !== null && (int) $agentId !== $own->id)) {
                throw ListingException::agentOutsideAgency();
            }

            return $own;
        }

        if ($agentId === null) {
            return null;
        }

        $agent = Agent::query()->whereKey($agentId)->where('status', 'active')->first();
        if ($agent === null || ($property?->agency_id && $agent->agency_id !== $property->agency_id)) {
            throw ListingException::agentOutsideAgency();
        }

        return $agent;
    }

    private function recordHistory(Property $property, ?string $fromStatus, mixed $fromPrice, User $actor): void
    {
        PropertyStatusHistory::query()->create([
            'property_id' => $property->id,
            'from_status' => $fromStatus,
            'to_status' => $property->status->value,
            'from_price' => $fromPrice,
            'to_price' => $property->list_price,
            'source' => PropertySource::Internal->value,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn ($v) => is_string($v) && json_validate($v) && in_array($v[0] ?? '', ['[', '{'], true) ? json_decode($v, true) : $v, $values);
    }
}
