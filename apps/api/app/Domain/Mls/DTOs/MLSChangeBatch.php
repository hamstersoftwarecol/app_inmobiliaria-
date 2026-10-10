<?php

namespace App\Domain\Mls\DTOs;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Result of an incremental sync call: the changed listings plus the opaque
 * cursor to persist for the next call.
 *
 * @implements IteratorAggregate<int, MLSListingDTO>
 */
final class MLSChangeBatch implements IteratorAggregate
{
    /**
     * @param  list<MLSListingDTO>  $listings
     */
    public function __construct(
        public readonly array $listings,
        public readonly ?string $nextCursor,
        public readonly bool $hasMore = false,
    ) {}

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->listings);
    }
}
