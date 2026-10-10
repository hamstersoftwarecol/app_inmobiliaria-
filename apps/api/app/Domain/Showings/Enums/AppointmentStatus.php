<?php

namespace App\Domain\Showings\Enums;

enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    /** Holds the agent's time (covered by the no-overlap constraint). */
    public function isLive(): bool
    {
        return in_array($this, [self::Requested, self::Confirmed], true);
    }
}
