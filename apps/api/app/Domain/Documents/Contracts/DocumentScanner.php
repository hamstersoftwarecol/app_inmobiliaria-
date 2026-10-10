<?php

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\Enums\ScanStatus;

interface DocumentScanner
{
    /**
     * @param  resource  $stream  readable stream positioned at the start
     * @return array{0: ScanStatus, 1: string|null} status and detail (e.g. signature name)
     */
    public function scan($stream): array;

    public function isConfigured(): bool;
}
