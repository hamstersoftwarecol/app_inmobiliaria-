<?php

namespace App\Domain\Deals\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Countered = 'countered';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    // The accepted offer's transaction later closed or fell through.
    case Closed = 'closed';
    case Terminated = 'terminated';

    public function awaitsResponse(): bool
    {
        return $this === self::Submitted;
    }
}
