<?php

namespace App\Infrastructure\Mls\Providers;

use App\Application\Mls\MLSNormalizationService;
use App\Domain\Mls\Contracts\MLSProviderInterface;
use App\Domain\Mls\DTOs\MLSChangeBatch;
use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Domain\Mls\DTOs\ProviderCapabilities;
use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsConnection;

/**
 * Reads RESO-shaped JSON fixtures from disk so the sync pipeline can be
 * developed and tested without real MLS access. Refused outside
 * local/testing environments by MLSConnectionManager and by itself.
 *
 * Fixture files: <fixture_path>/property.json and media.json ({"value": [...]}).
 */
class FixtureMLSProvider implements MLSProviderInterface
{
    public function __construct(
        private readonly MlsConnection $connection,
        private readonly MLSNormalizationService $normalizer,
    ) {
        if (! app()->environment(['local', 'testing'])) {
            throw MLSException::fixturesNotAllowed();
        }
    }

    public function code(): string
    {
        return $this->connection->provider?->code ?? 'fixture';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities('fixture', supportsMetadata: false, supportsMedia: true, supportsIncremental: true, specificationVerified: true);
    }

    public function authenticate(): void {}

    public function fetchMetadata(): ?string
    {
        return null;
    }

    public function fetchListings(array $filters = []): iterable
    {
        foreach ($this->records('property.json') as $record) {
            if (! empty($filters['status']) && ! in_array($record['StandardStatus'] ?? null, (array) $filters['status'], true)) {
                continue;
            }
            yield $this->normalizer->normalizeListing($this->code(), $record);
        }
    }

    public function fetchListing(string $listingKey): ?MLSListingDTO
    {
        foreach ($this->records('property.json') as $record) {
            if (($record['ListingKey'] ?? null) === $listingKey) {
                return $this->normalizer->normalizeListing($this->code(), $record);
            }
        }

        return null;
    }

    public function fetchMedia(string $listingKey): iterable
    {
        foreach ($this->records('media.json') as $record) {
            if (($record['ResourceRecordKey'] ?? null) === $listingKey
                && ($media = $this->normalizer->normalizeMedia($listingKey, $record)) !== null) {
                yield $media;
            }
        }
    }

    public function fetchChanges(?string $cursor = null): MLSChangeBatch
    {
        $records = $this->records('property.json');
        usort($records, fn ($a, $b) => [$a['ModificationTimestamp'] ?? '', $a['ListingKey'] ?? ''] <=> [$b['ModificationTimestamp'] ?? '', $b['ListingKey'] ?? '']);

        $listings = [];
        $next = $cursor;
        foreach ($records as $record) {
            $position = ($record['ModificationTimestamp'] ?? '').'|'.($record['ListingKey'] ?? '');
            if ($cursor === null || strcmp($position, $cursor) > 0) {
                $listings[] = $this->normalizer->normalizeListing($this->code(), $record);
                $next = $position;
            }
        }

        return new MLSChangeBatch($listings, $next, false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(string $file): array
    {
        $path = rtrim((string) config('mls.fixture_path'), '/').'/'.$file;
        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['value'] ?? [];
    }
}
