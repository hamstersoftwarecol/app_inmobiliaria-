<?php

namespace Tests\Unit\Mls;

use App\Domain\Listings\Enums\PropertyTypeCode;
use PHPUnit\Framework\TestCase;

class PropertyTypeCodeTest extends TestCase
{
    public function test_maps_reso_types_and_subtypes(): void
    {
        $this->assertSame(PropertyTypeCode::SingleFamily, PropertyTypeCode::fromReso('Residential', 'Single Family Residence'));
        $this->assertSame(PropertyTypeCode::Condominium, PropertyTypeCode::fromReso('Residential', 'Condominium'));
        $this->assertSame(PropertyTypeCode::Townhouse, PropertyTypeCode::fromReso('Residential', 'Townhouse'));
        $this->assertSame(PropertyTypeCode::MultiFamily, PropertyTypeCode::fromReso('Residential Income', 'Duplex'));
        $this->assertSame(PropertyTypeCode::Rental, PropertyTypeCode::fromReso('Residential Lease', 'Single Family Residence'));
        $this->assertSame(PropertyTypeCode::Rental, PropertyTypeCode::fromReso('Residential Lease', null));
        $this->assertSame(PropertyTypeCode::Land, PropertyTypeCode::fromReso('Land', 'Unimproved Land'));
        $this->assertSame(PropertyTypeCode::Commercial, PropertyTypeCode::fromReso('Commercial Sale', 'Office'));
        $this->assertNull(PropertyTypeCode::fromReso('Something Else', null));
    }
}
