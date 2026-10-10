<?php

namespace App\Domain\Crm\Enums;

enum LeadSource: string
{
    case WebsiteListing = 'website_listing';
    case WebsiteAgent = 'website_agent';
    case WebsiteContact = 'website_contact';
    case Phone = 'phone';
    case Email = 'email';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Social = 'social';
    case Other = 'other';

    /** Sources staff may choose when entering a lead by hand. */
    public static function manual(): array
    {
        return [self::Phone, self::Email, self::WalkIn, self::Referral, self::Social, self::Other];
    }
}
