<?php

namespace App\Domain\Search\Enums;

enum AlertFrequency: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
