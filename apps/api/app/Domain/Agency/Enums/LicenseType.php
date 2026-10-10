<?php

namespace App\Domain\Agency\Enums;

/**
 * New Jersey real estate licence categories.
 */
enum LicenseType: string
{
    case Salesperson = 'salesperson';
    case BrokerSalesperson = 'broker_salesperson';
    case Broker = 'broker';
    case ReferralAgent = 'referral_agent';

    public function label(): string
    {
        return match ($this) {
            self::Salesperson => 'Salesperson',
            self::BrokerSalesperson => 'Broker-Salesperson',
            self::Broker => 'Broker',
            self::ReferralAgent => 'Referral Agent',
        };
    }
}
