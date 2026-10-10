<?php

namespace App\Application\Mls;

use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Domain\Mls\DTOs\MLSMediaDTO;
use App\Domain\Mls\Enums\ListingStatus;
use DateTimeImmutable;
use Throwable;

/**
 * Maps RESO Data Dictionary payloads to provider-neutral DTOs.
 *
 * Providers that deviate from the Data Dictionary pass a field map
 * (internal name => provider field) which overrides the defaults; the
 * per-provider map will be stored in `mls_field_mappings` (Phase 4).
 */
class MLSNormalizationService
{
    private const SQFT_PER_ACRE = 43560.0;

    /** @var array<string, string> internal => RESO Data Dictionary field */
    private const DEFAULT_MAP = [
        'listing_key' => 'ListingKey',
        'listing_id' => 'ListingId',
        'originating_system' => 'OriginatingSystemName',
        'status' => 'StandardStatus',
        'property_type' => 'PropertyType',
        'property_subtype' => 'PropertySubType',
        'list_price' => 'ListPrice',
        'unparsed_address' => 'UnparsedAddress',
        'street_number' => 'StreetNumber',
        'street_dir_prefix' => 'StreetDirPrefix',
        'street_name' => 'StreetName',
        'street_suffix' => 'StreetSuffix',
        'unit_number' => 'UnitNumber',
        'city' => 'City',
        'county' => 'CountyOrParish',
        'state' => 'StateOrProvince',
        'postal_code' => 'PostalCode',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'bedrooms' => 'BedroomsTotal',
        'bathrooms_total' => 'BathroomsTotalDecimal',
        'bathrooms_integer' => 'BathroomsTotalInteger',
        'bathrooms_full' => 'BathroomsFull',
        'bathrooms_half' => 'BathroomsHalf',
        'living_area' => 'LivingArea',
        'living_area_units' => 'LivingAreaUnits',
        'lot_size_sqft' => 'LotSizeSquareFeet',
        'lot_size_acres' => 'LotSizeAcres',
        'year_built' => 'YearBuilt',
        'parking_total' => 'ParkingTotal',
        'garage_spaces' => 'GarageSpaces',
        'heating' => 'Heating',
        'cooling' => 'Cooling',
        'association_fee' => 'AssociationFee',
        'association_fee_frequency' => 'AssociationFeeFrequency',
        'tax_annual_amount' => 'TaxAnnualAmount',
        'public_remarks' => 'PublicRemarks',
        'list_agent_name' => 'ListAgentFullName',
        'list_office_name' => 'ListOfficeName',
        'internet_display' => 'InternetEntireListingDisplayYN',
        'address_display' => 'InternetAddressDisplayYN',
        'modified_at' => 'ModificationTimestamp',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $fieldMap  overrides of DEFAULT_MAP
     */
    public function normalizeListing(string $sourceMls, array $payload, array $fieldMap = [], ?string $attribution = null): MLSListingDTO
    {
        $get = fn (string $key) => $payload[($fieldMap + self::DEFAULT_MAP)[$key] ?? $key] ?? null;

        $listingKey = (string) ($get('listing_key') ?? $get('listing_id') ?? '');
        if ($listingKey === '') {
            throw new \InvalidArgumentException('MLS payload has no ListingKey/ListingId.');
        }

        $listAgent = $this->string($get('list_agent_name'));
        $listOffice = $this->string($get('list_office_name'));

        return new MLSListingDTO(
            sourceMls: $sourceMls,
            listingKey: $listingKey,
            listingId: $this->string($get('listing_id')),
            originatingSystem: $this->string($get('originating_system')),
            status: ListingStatus::fromReso($this->string($get('status')))->value,
            propertyType: $this->string($get('property_type')),
            propertySubtype: $this->string($get('property_subtype')),
            listPrice: $this->float($get('list_price')),
            currency: 'USD',
            streetAddress: $this->streetAddress($get),
            unitNumber: $this->string($get('unit_number')),
            city: $this->string($get('city')),
            county: $this->county($this->string($get('county'))),
            state: $this->string($get('state')) !== null ? strtoupper((string) $this->string($get('state'))) : null,
            postalCode: $this->postalCode($this->string($get('postal_code'))),
            latitude: $this->coordinate($get('latitude'), 90),
            longitude: $this->coordinate($get('longitude'), 180),
            bedrooms: $this->int($get('bedrooms')),
            bathrooms: $this->bathrooms($get),
            livingAreaSqft: $this->livingArea($get),
            lotSizeSqft: $this->lotSize($get),
            yearBuilt: $this->yearBuilt($get('year_built')),
            parkingSpaces: $this->int($get('parking_total')),
            garageSpaces: $this->int($get('garage_spaces')),
            heating: $this->list($get('heating')),
            cooling: $this->list($get('cooling')),
            hoaFee: $this->monthlyFee($this->float($get('association_fee')), $this->string($get('association_fee_frequency'))),
            annualTaxes: $this->float($get('tax_annual_amount')),
            description: $this->string($get('public_remarks')),
            listAgentName: $listAgent,
            listBrokerName: $listOffice,
            attribution: $attribution !== null
                ? strtr($attribution, ['{broker}' => (string) $listOffice, '{agent}' => (string) $listAgent])
                : ($listOffice !== null ? "Listing courtesy of {$listOffice}" : null),
            internetDisplayAllowed: $this->bool($get('internet_display'), true),
            addressDisplayAllowed: $this->bool($get('address_display'), true),
            modifiedAt: $this->date($get('modified_at')),
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload  RESO Media resource record
     */
    public function normalizeMedia(string $listingKey, array $payload): ?MLSMediaDTO
    {
        $url = $this->string($payload['MediaURL'] ?? null);
        $key = $this->string($payload['MediaKey'] ?? null);
        if ($url === null || $key === null || ! str_starts_with($url, 'https://')) {
            return null;
        }

        $permission = strtolower((string) ($payload['Permission'] ?? ''));

        return new MLSMediaDTO(
            mediaKey: $key,
            listingKey: $listingKey,
            url: $url,
            order: (int) ($payload['Order'] ?? 0),
            category: $this->string($payload['MediaCategory'] ?? null),
            caption: $this->string($payload['ShortDescription'] ?? null),
            mimeType: $this->string($payload['MimeType'] ?? null),
            modifiedAt: $this->date($payload['MediaModificationTimestamp'] ?? null),
            displayAllowed: ! in_array($permission, ['private', 'agent only', 'office only'], true),
        );
    }

    private function streetAddress(callable $get): ?string
    {
        $parts = array_filter([
            $this->string($get('street_number')),
            $this->string($get('street_dir_prefix')),
            $this->string($get('street_name')),
            $this->string($get('street_suffix')),
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        $unparsed = $this->string($get('unparsed_address'));

        return $unparsed !== null ? trim(explode(',', $unparsed)[0]) : null;
    }

    private function bathrooms(callable $get): ?float
    {
        $total = $this->float($get('bathrooms_total')) ?? $this->float($get('bathrooms_integer'));
        if ($total !== null) {
            return $total;
        }

        $full = $this->int($get('bathrooms_full'));
        $half = $this->int($get('bathrooms_half'));

        return ($full === null && $half === null) ? null : (float) ($full ?? 0) + 0.5 * ($half ?? 0);
    }

    private function livingArea(callable $get): ?float
    {
        $area = $this->float($get('living_area'));
        $units = strtolower((string) $this->string($get('living_area_units')));

        return ($area !== null && str_contains($units, 'meter')) ? round($area * 10.7639, 2) : $area;
    }

    private function lotSize(callable $get): ?float
    {
        $sqft = $this->float($get('lot_size_sqft'));
        if ($sqft !== null) {
            return $sqft;
        }

        $acres = $this->float($get('lot_size_acres'));

        return $acres !== null ? round($acres * self::SQFT_PER_ACRE, 2) : null;
    }

    private function monthlyFee(?float $fee, ?string $frequency): ?float
    {
        if ($fee === null) {
            return null;
        }

        return match (strtolower((string) $frequency)) {
            'annually', 'annual', 'yearly' => round($fee / 12, 2),
            'quarterly' => round($fee / 3, 2),
            'semi-annually', 'semiannually' => round($fee / 6, 2),
            default => $fee,
        };
    }

    private function county(?string $county): ?string
    {
        return $county !== null ? trim(preg_replace('/\s+county$/i', '', $county) ?? $county) : null;
    }

    private function postalCode(?string $zip): ?string
    {
        return $zip !== null && preg_match('/^(\d{5})(?:-?\d{4})?$/', $zip, $m) ? $m[1] : $zip;
    }

    private function coordinate(mixed $value, int $limit): ?float
    {
        $f = $this->float($value);

        return ($f !== null && abs($f) <= $limit && $f !== 0.0) ? $f : null;
    }

    private function yearBuilt(mixed $value): ?int
    {
        $year = $this->int($value);

        return ($year !== null && $year >= 1600 && $year <= (int) date('Y') + 3) ? $year : null;
    }

    private function string(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function bool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower((string) $value)) {
            'true', 'y', 'yes', '1' => true,
            'false', 'n', 'no', '0' => false,
            default => $default,
        };
    }

    /**
     * @return list<string>
     */
    private function list(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return is_array($value) ? array_values(array_filter(array_map(fn ($v) => trim((string) $v), $value))) : [];
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
