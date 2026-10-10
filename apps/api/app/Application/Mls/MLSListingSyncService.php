<?php

namespace App\Application\Mls;

use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Models\MlsConnection;
use App\Models\MlsListing;
use App\Models\MlsSyncJob;
use App\Models\MlsSyncLog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applies normalized MLS records to the database.
 *
 * - Each record is applied in its own transaction: one bad record never
 *   rolls back or blocks the others, and existing data is only replaced
 *   by a successfully normalized newer version.
 * - Deduplication: records are keyed by (provider, ListingKey); an
 *   identical payload (same content hash) is a no-op.
 *
 * @see MLSDeduplicationService for cross-record duplicate detection.
 */
class MLSListingSyncService
{
    public function __construct(private readonly ListingMaterializer $materializer) {}

    /**
     * @param  iterable<MLSListingDTO>  $listings
     * @return list<string> listing keys created or updated (media refresh candidates)
     */
    public function apply(MlsSyncJob $job, MlsConnection $connection, iterable $listings, SyncStats $stats): array
    {
        $changed = [];

        foreach ($listings as $dto) {
            $stats->fetched++;

            try {
                $result = DB::transaction(fn () => $this->applyOne($connection, $dto));
            } catch (Throwable $e) {
                $stats->errors++;
                $this->log($job, 'error', 'Record could not be applied: '.mb_substr($e->getMessage(), 0, 300), $dto->listingKey);

                continue;
            }

            match ($result['outcome']) {
                'created' => $stats->created++,
                'updated' => $stats->updated++,
                default => $stats->unchanged++,
            };
            if ($result['outcome'] !== 'unchanged') {
                $result['published'] ? $stats->published++ : $stats->hidden++;
                $changed[] = $dto->listingKey;
            }
        }

        return $changed;
    }

    /**
     * @return array{outcome: string, published: bool}
     */
    private function applyOne(MlsConnection $connection, MLSListingDTO $dto): array
    {
        $hash = hash('sha256', json_encode($this->canonical($dto->raw), JSON_THROW_ON_ERROR));

        $listing = MlsListing::query()
            ->where('mls_provider_id', $connection->mls_provider_id)
            ->where('listing_key', $dto->listingKey)
            ->lockForUpdate()
            ->first();

        if ($listing && $listing->content_hash === $hash && $listing->removed_at === null) {
            $listing->forceFill(['last_synced_at' => now()])->save();

            return ['outcome' => 'unchanged', 'published' => false];
        }

        // Never let an older payload overwrite a newer one (out-of-order delivery).
        if ($listing && $listing->source_modified_at && $dto->modifiedAt && $dto->modifiedAt < $listing->source_modified_at) {
            return ['outcome' => 'unchanged', 'published' => false];
        }

        $created = $listing === null;
        $listing ??= new MlsListing(['mls_provider_id' => $connection->mls_provider_id, 'listing_key' => $dto->listingKey]);
        $listing->fill([
            'listing_id' => $dto->listingId,
            'originating_system' => $dto->originatingSystem,
            'status' => $dto->status,
            'list_price' => $dto->listPrice,
            'listing_broker' => $dto->listBrokerName,
            'listing_agent' => $dto->listAgentName,
            'attribution' => $dto->attribution,
            'internet_display_allowed' => $dto->internetDisplayAllowed,
            'address_display_allowed' => $dto->addressDisplayAllowed,
            'source_modified_at' => $dto->modifiedAt,
            'last_synced_at' => now(),
            'removed_at' => null,
            'content_hash' => $hash,
            'raw_payload' => $dto->raw,
        ])->save();

        $result = $this->materializer->materialize($connection, $listing, $dto);

        return ['outcome' => $created ? 'created' : 'updated', 'published' => $result['published']];
    }

    public function log(MlsSyncJob $job, string $level, string $message, ?string $listingKey = null, array $context = []): void
    {
        MlsSyncLog::query()->create([
            'mls_sync_job_id' => $job->id,
            'level' => $level,
            'message' => mb_substr($message, 0, 500),
            'listing_key' => $listingKey !== null ? mb_substr($listingKey, 0, 100) : null,
            'context' => $context ?: null,
        ]);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonical(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->canonical($v);
            }
        }

        return $value;
    }
}
