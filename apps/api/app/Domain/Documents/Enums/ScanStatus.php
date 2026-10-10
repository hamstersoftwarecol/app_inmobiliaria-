<?php

namespace App\Domain\Documents\Enums;

enum ScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Unscanned = 'unscanned'; // no scanner configured
    case Error = 'error';

    public function downloadable(bool $scanRequired): bool
    {
        return match ($this) {
            self::Clean => true,
            self::Unscanned => ! $scanRequired,
            default => false,
        };
    }
}
