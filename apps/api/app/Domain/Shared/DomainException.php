<?php

namespace App\Domain\Shared;

use RuntimeException;

/**
 * Business-rule violation rendered as the standard JSON error envelope:
 * {"error": {"code": "...", "message": "...", "details": {...}}}.
 */
class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
