<?php

namespace App\Domain\Mls\DTOs;

final readonly class ProviderCapabilities
{
    public function __construct(
        public string $protocol,
        public bool $supportsMetadata = false,
        public bool $supportsMedia = false,
        public bool $supportsIncremental = false,
        public bool $specificationVerified = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
