<?php

namespace App\Domain\Mls\DTOs;

final readonly class MLSMediaDTO
{
    public function __construct(
        public string $mediaKey,
        public string $listingKey,
        public string $url,
        public int $order = 0,
        public ?string $category = null,
        public ?string $caption = null,
        public ?string $mimeType = null,
        public ?\DateTimeImmutable $modifiedAt = null,
        public bool $displayAllowed = true,
    ) {}
}
