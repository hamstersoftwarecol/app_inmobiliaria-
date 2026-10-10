<?php

namespace App\Domain\Deals\Enums;

enum TransactionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
