<?php

namespace App\Application\Mls;

use App\Domain\Listings\Enums\ListingType;
use App\Domain\Listings\Enums\PropertySource;
use App\Domain\Listings\Enums\PropertyTypeCode;
use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Domain\Mls\Enums\ListingStatus;
use App\Models\MlsConnection;
use App\Models\MlsListing;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Models\PropertyType;
use App\Support\UniqueSlug;

/**
 * Projects an MLS record onto the `properties` table used by the site.
 *
 * Publication is decided here, never by staff: only production connections
 * of authorized providers whose display rules allow it are published, and
 * off-market/opted-out records are hidden (not deleted).
 */
class ListingMaterializer
{
    public function __construct(private readonly MLSComplianceService $compliance) {}

    /**
     * @return array{property: Property, created: bool, published: bool}
     */
    public function materialize(MlsConnection $connection, MlsListing $listing, MLSListingDTO $dto): array
    {
        $rules = $connection->provider->displayRule;
        /** @var Property $property */
        $property = $listing->property()->first() ?? new Property;
        $created = ! $property->exists;
        $previousStatus = $property->status;
        $previousPrice = $property->list_price;

        $typeCode = PropertyTypeCode::fromReso($dto->propertyType, $dto->propertySubtype);
        $isLease = str_contains(strtolower((string) $dto->propertyType), 'lease');

        $property->forceFill([
            'source' => PropertySource::Mls,
            'mls_listing_id' => $listing->id,
            'property_type_id' => $typeCode ? PropertyType::query()->where('code', $typeCode->value)->value('id') : null,
            'property_subtype' => $dto->propertySubtype,
            'listing_type' => $isLease ? ListingType::Rent : ListingType::Sale,
            'status' => ListingStatus::tryFrom($dto->status) ?? ListingStatus::Unknown,
            'title' => $this->title($dto),
            'description' => $dto->description,
            'list_price' => $dto->listPrice,
            'currency' => $dto->currency,
            'street_address' => $dto->streetAddress,
            'unit' => $dto->unitNumber,
            'city' => $dto->city,
            'county' => $dto->county,
            'state' => $dto->state ?? 'NJ',
            'postal_code' => $dto->postalCode,
            'address_display' => $dto->addressDisplayAllowed,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'bedrooms' => $dto->bedrooms,
            'bathrooms' => $dto->bathrooms,
            'living_area_sqft' => $dto->livingAreaSqft !== null ? (int) round($dto->livingAreaSqft) : null,
            'lot_size_sqft' => $dto->lotSizeSqft,
            'year_built' => $dto->yearBuilt,
            'parking_spaces' => $dto->parkingSpaces,
            'garage_spaces' => $dto->garageSpaces,
            'heating' => $dto->heating,
            'cooling' => $dto->cooling,
            'hoa_fee_monthly' => $dto->hoaFee,
            'annual_taxes' => $dto->annualTaxes,
            'deleted_at' => null,
        ]);

        if ($created) {
            $property->slug = UniqueSlug::for(implode(' ', array_filter([$dto->streetAddress, $dto->city, $dto->state, $dto->listingId ?? $dto->listingKey])), Property::query());
        }

        $publish = $connection->isProduction() && $listing->removed_at === null && $this->compliance->canDisplay($dto, $rules);
        if ($publish && ! $property->is_published) {
            $property->published_at = now();
        }
        $property->is_published = $publish;
        $property->save();

        if ($created || $previousStatus !== $property->status || (string) $previousPrice !== (string) $property->list_price) {
            PropertyStatusHistory::query()->create([
                'property_id' => $property->id,
                'from_status' => $created ? null : $previousStatus?->value,
                'to_status' => $property->status->value,
                'from_price' => $created ? null : $previousPrice,
                'to_price' => $property->list_price,
                'source' => PropertySource::Mls->value,
                'changed_at' => now(),
            ]);
        }

        return ['property' => $property, 'created' => $created, 'published' => $publish];
    }

    /**
     * Hides the property of a listing that disappeared from the feed.
     */
    public function hide(MlsListing $listing): void
    {
        $property = $listing->property()->first();
        if ($property && $property->is_published) {
            $property->forceFill(['is_published' => false])->save();
        }
    }

    private function title(MLSListingDTO $dto): string
    {
        $place = $dto->addressDisplayAllowed && $dto->streetAddress ? "{$dto->streetAddress}, {$dto->city}" : (string) $dto->city;
        $kind = $dto->propertySubtype ?? $dto->propertyType ?? 'Property';

        return mb_substr(trim("{$kind} — {$place}", ' —,'), 0, 200);
    }
}
