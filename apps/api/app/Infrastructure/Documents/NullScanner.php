<?php

namespace App\Infrastructure\Documents;

use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\Enums\ScanStatus;

/** No scanner configured: files are recorded as unscanned (shown as such to users). */
final class NullScanner implements DocumentScanner
{
    public function scan($stream): array
    {
        return [ScanStatus::Unscanned, null];
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
