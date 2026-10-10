<?php

namespace App\Domain\Mls\Contracts;

use App\Domain\Mls\DTOs\MLSChangeBatch;
use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Domain\Mls\DTOs\MLSMediaDTO;
use App\Domain\Mls\DTOs\ProviderCapabilities;
use App\Domain\Mls\Exceptions\MLSException;

/**
 * Contract every MLS adapter implements. Providers differ in protocol and in
 * what they license, so callers must consult capabilities() before relying on
 * optional operations (metadata, media, incremental changes).
 */
interface MLSProviderInterface
{
    /** Stable provider code, e.g. "njmls", "gsmls", "bright", "reso". */
    public function code(): string;

    public function capabilities(): ProviderCapabilities;

    /**
     * Obtains/refreshes credentials (e.g. OAuth2 client-credentials token).
     *
     * @throws MLSException
     */
    public function authenticate(): void;

    /**
     * Raw metadata document (e.g. RESO $metadata EDMX) when supported.
     */
    public function fetchMetadata(): ?string;

    /**
     * Full/initial import. Implementations must page lazily.
     *
     * @param  array<string, mixed>  $filters  provider-neutral filters (status, modified_since, page_size)
     * @return iterable<MLSListingDTO>
     */
    public function fetchListings(array $filters = []): iterable;

    public function fetchListing(string $listingKey): ?MLSListingDTO;

    /**
     * @return iterable<MLSMediaDTO>
     */
    public function fetchMedia(string $listingKey): iterable;

    /**
     * Incremental changes since the opaque cursor returned by the previous batch.
     */
    public function fetchChanges(?string $cursor = null): MLSChangeBatch;
}
