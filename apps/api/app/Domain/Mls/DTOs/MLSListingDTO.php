<?php

namespace App\Domain\Mls\DTOs;

/**
 * Provider-neutral listing. Keeps compliance fields (source, keys, broker,
 * attribution, timestamps, display permissions) next to the normalized data,
 * and the untouched payload so original values are never lost.
 */
final readonly class MLSListingDTO
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $heating
     * @param  list<string>  $cooling
     */
    public function __construct(
        public string $sourceMls,
        public string $listingKey,
        public ?string $listingId,
        public ?string $originatingSystem,
        public string $status,
        public ?string $propertyType,
        public ?string $propertySubtype,
        public ?float $listPrice,
        public string $currency,
        public ?string $streetAddress,
        public ?string $unitNumber,
        public ?string $city,
        public ?string $county,
        public ?string $state,
        public ?string $postalCode,
        public ?float $latitude,
        public ?float $longitude,
        public ?int $bedrooms,
        public ?float $bathrooms,
        public ?float $livingAreaSqft,
        public ?float $lotSizeSqft,
        public ?int $yearBuilt,
        public ?int $parkingSpaces,
        public ?int $garageSpaces,
        public array $heating,
        public array $cooling,
        public ?float $hoaFee,
        public ?float $annualTaxes,
        public ?string $description,
        public ?string $listAgentName,
        public ?string $listBrokerName,
        public ?string $attribution,
        public bool $internetDisplayAllowed,
        public bool $addressDisplayAllowed,
        public ?\DateTimeImmutable $modifiedAt,
        public array $raw = [],
    ) {}

    /**
     * Composite identity: listing keys are only unique inside one MLS.
     */
    public function compositeKey(): string
    {
        return $this->sourceMls.':'.$this->listingKey;
    }
}
