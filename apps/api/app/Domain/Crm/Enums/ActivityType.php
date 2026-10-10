<?php

namespace App\Domain\Crm\Enums;

enum ActivityType: string
{
    // Logged by people.
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    // Recorded by the system.
    case Inquiry = 'inquiry';
    case StatusChange = 'status_change';
    case Assignment = 'assignment';
    case Merge = 'merge';

    /**
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Note, self::Call, self::Email, self::Meeting];
    }

    /** Counts as a response to the client (first-response time). */
    public function isContact(): bool
    {
        return in_array($this, [self::Call, self::Email, self::Meeting], true);
    }
}
