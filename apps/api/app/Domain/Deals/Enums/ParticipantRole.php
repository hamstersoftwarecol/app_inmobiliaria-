<?php

namespace App\Domain\Deals\Enums;

enum ParticipantRole: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Attorney = 'attorney';
    case Lender = 'lender';
    case Title = 'title';
    case Other = 'other';
}
