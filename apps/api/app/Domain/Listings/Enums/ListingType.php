<?php

namespace App\Domain\Listings\Enums;

enum ListingType: string
{
    case Sale = 'sale';
    case Rent = 'rent';
}
