<?php

namespace App\Domain\Crm\Enums;

/**
 * Pipeline stages (Kanban columns), in order. `won` and `lost` close the lead;
 * a closed lead can be reopened by moving it back to an open stage.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Showing = 'showing';
    case Negotiating = 'negotiating';
    case Won = 'won';
    case Lost = 'lost';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isOpen())));
    }
}
