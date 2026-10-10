<?php

namespace App\Domain\Listings\Enums;

/**
 * Internal property categories, with a mapping from RESO PropertyType /
 * PropertySubType values used by MLS feeds.
 */
enum PropertyTypeCode: string
{
    case SingleFamily = 'single_family';
    case Condominium = 'condominium';
    case Townhouse = 'townhouse';
    case MultiFamily = 'multi_family';
    case Apartment = 'apartment';
    case Land = 'land';
    case Commercial = 'commercial';
    case Rental = 'rental';

    /**
     * @return array{0: string, 1: string} [English, Spanish]
     */
    public function names(): array
    {
        return match ($this) {
            self::SingleFamily => ['Single Family Home', 'Casa unifamiliar'],
            self::Condominium => ['Condominium', 'Condominio'],
            self::Townhouse => ['Townhouse', 'Casa adosada'],
            self::MultiFamily => ['Multi-Family Home', 'Multifamiliar'],
            self::Apartment => ['Apartment', 'Apartamento'],
            self::Land => ['Land', 'Terreno'],
            self::Commercial => ['Commercial Property', 'Propiedad comercial'],
            self::Rental => ['Rental Property', 'Propiedad en alquiler'],
        };
    }

    public static function fromReso(?string $propertyType, ?string $subType): ?self
    {
        $type = strtolower((string) $propertyType);
        $sub = strtolower((string) $subType);

        return match (true) {
            str_contains($sub, 'condo') => self::Condominium,
            str_contains($sub, 'town') => self::Townhouse,
            str_contains($sub, 'apartment') => self::Apartment,
            str_contains($sub, 'single family') => $type === 'residential lease' ? self::Rental : self::SingleFamily,
            str_contains($type, 'residential income') || str_contains($sub, 'duplex') || str_contains($sub, 'triplex') || str_contains($sub, 'quadruplex') => self::MultiFamily,
            str_contains($type, 'land') || str_contains($type, 'farm') => self::Land,
            str_contains($type, 'commercial') || str_contains($type, 'business') => self::Commercial,
            $type === 'residential lease' => self::Rental,
            $type === 'residential' => self::SingleFamily,
            default => null,
        };
    }
}
