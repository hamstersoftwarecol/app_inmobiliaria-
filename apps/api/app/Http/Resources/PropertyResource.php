<?php

namespace App\Http\Resources;

use App\Application\Listings\PropertyService;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Staff representation (dashboard). Public pages use PublicPropertyResource.
 *
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'source' => $this->source->value,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
            'publication_missing' => $this->isMls() ? [] : app(PropertyService::class)->missingForPublication($this->resource),
            'status' => $this->status->value,
            'listing_type' => $this->listing_type->value,
            'property_type' => $this->whenLoaded('propertyType', fn () => $this->propertyType ? [
                'id' => $this->propertyType->id, 'code' => $this->propertyType->code, 'name_en' => $this->propertyType->name_en, 'name_es' => $this->propertyType->name_es,
            ] : null),
            'property_type_id' => $this->property_type_id,
            'property_subtype' => $this->property_subtype,
            'title' => $this->title,
            'description' => $this->description,
            'description_es' => $this->description_es,
            'list_price' => $this->list_price !== null ? (float) $this->list_price : null,
            'currency' => $this->currency,
            'street_address' => $this->street_address,
            'unit' => $this->unit,
            'city' => $this->city,
            'county' => $this->county,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'address_display' => $this->address_display,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms !== null ? (float) $this->bathrooms : null,
            'living_area_sqft' => $this->living_area_sqft,
            'lot_size_sqft' => $this->lot_size_sqft !== null ? (float) $this->lot_size_sqft : null,
            'year_built' => $this->year_built,
            'parking_spaces' => $this->parking_spaces,
            'garage_spaces' => $this->garage_spaces,
            'heating' => $this->heating ?? [],
            'cooling' => $this->cooling ?? [],
            'amenities' => $this->amenities ?? [],
            'hoa_fee_monthly' => $this->hoa_fee_monthly !== null ? (float) $this->hoa_fee_monthly : null,
            'annual_taxes' => $this->annual_taxes !== null ? (float) $this->annual_taxes : null,
            'listing_agent' => $this->whenLoaded('listingAgent', fn () => $this->listingAgent ? [
                'id' => $this->listingAgent->id, 'full_name' => $this->listingAgent->user?->full_name, 'slug' => $this->listingAgent->slug,
            ] : null),
            'mls' => $this->whenLoaded('mlsListing', fn () => $this->mlsListing ? [
                'provider' => $this->mlsListing->provider?->name,
                'listing_id' => $this->mlsListing->listing_id,
                'listing_key' => $this->mlsListing->listing_key,
                'listing_broker' => $this->mlsListing->listing_broker,
                'last_synced_at' => $this->mlsListing->last_synced_at?->toIso8601String(),
                'removed_at' => $this->mlsListing->removed_at?->toIso8601String(),
            ] : null),
            'media' => PropertyMediaResource::collection($this->whenLoaded('media')),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn (PropertyStatusHistory $h) => [
                'from_status' => $h->from_status, 'to_status' => $h->to_status,
                'from_price' => $h->from_price !== null ? (float) $h->from_price : null,
                'to_price' => $h->to_price !== null ? (float) $h->to_price : null,
                'source' => $h->source, 'changed_at' => $h->changed_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
