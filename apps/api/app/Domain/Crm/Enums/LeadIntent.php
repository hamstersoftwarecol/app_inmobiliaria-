<?php

namespace App\Domain\Crm\Enums;

enum LeadIntent: string
{
    case Buy = 'buy';
    case Rent = 'rent';
    case Sell = 'sell';
    case Other = 'other';
}
