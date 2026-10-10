<?php

namespace App\Application\Mls;

use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Domain\Mls\Contracts\MLSProviderInterface;
use App\Domain\Mls\DTOs\MLSListingDTO;
use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsConnection;
use App\Models\MlsListing;
use App\Models\MlsSyncJob;
use Throwable;

/**
 * Executes one recorded sync job. Cursor and timestamps are only advanced
 * after the records of a batch were applied, so a failure part-way resumes
 * from the last good position and never discards valid data.
 */
class MLSSyncRunner
{
    public function __construct(
        private readonly MLSConnectionResolver $resolver,
        private readonly MLSListingSyncService $sync,
        private readonly MLSMediaService $media,
        private readonly ListingMaterializer $materializer,
    ) {}

    public function run(MlsSyncJob $job): MlsSyncJob
    {
        $connection = $job->connection()->with('provider.displayRule')->firstOrFail();
        $stats = new SyncStats;
        $job->forceFill(['status' => 'running', 'started_at' => now(), 'cursor_before' => $connection->last_cursor, 'error' => null])->save();

        try {
            $provider = $this->resolver->resolve($connection);

            match ($job->type) {
                'incremental' => $this->incremental($job, $connection, $provider, $stats),
                'import' => $this->import($job, $connection, $provider, $stats),
                'reconcile' => $this->reconcile($job, $connection, $provider, $stats),
                default => throw new \InvalidArgumentException("Unknown sync type [{$job->type}]."),
            };

            $connection->forceFill(['status' => 'verified', 'last_error' => null])->save();
            $job->forceFill([
                'status' => $stats->errors > 0 ? 'partial' : 'succeeded',
                'cursor_after' => $connection->last_cursor,
            ]);
        } catch (Throwable $e) {
            $message = $e instanceof MLSException ? $e->getMessage() : 'Unexpected error: '.mb_substr($e->getMessage(), 0, 300);
            $connection->forceFill(['status' => 'error', 'last_error' => $message])->save();
            $job->forceFill(['status' => 'failed', 'error' => $message, 'cursor_after' => $connection->last_cursor]);
            $this->sync->log($job, 'error', $message);

            if ($e instanceof MLSException && $e->errorCode === 'mls_rate_limited') {
                $job->forceFill(['stats' => $stats->toArray(), 'finished_at' => now()])->save();
                throw $e; // the queue job releases itself and retries later
            }
        }

        $job->forceFill(['stats' => $stats->toArray(), 'finished_at' => now()])->save();

        return $job;
    }

    private function incremental(MlsSyncJob $job, MlsConnection $connection, MLSProviderInterface $provider, SyncStats $stats): void
    {
        $maxBatches = (int) config('mls.sync.max_batches_per_run', 25);

        for ($i = 0; $i < $maxBatches; $i++) {
            $batch = $provider->fetchChanges($connection->last_cursor);
            $changed = $this->sync->apply($job, $connection, $batch->listings, $stats);
            $this->refreshMedia($job, $connection, $provider, $changed, $stats);

            $connection->forceFill(['last_cursor' => $batch->nextCursor, 'last_synced_at' => now()])->save();

            if (! $batch->hasMore || $batch->listings === []) {
                return;
            }
        }

        $this->sync->log($job, 'info', "Stopped after {$maxBatches} batches; the next run continues from the saved cursor.");
    }

    private function import(MlsSyncJob $job, MlsConnection $connection, MLSProviderInterface $provider, SyncStats $stats): void
    {
        $chunk = [];
        $cursor = null;
        $size = (int) config('mls.sync.import_chunk', 100);

        $flush = function () use (&$chunk, $job, $connection, $provider, $stats) {
            $changed = $this->sync->apply($job, $connection, $chunk, $stats);
            $this->refreshMedia($job, $connection, $provider, $changed, $stats);
            $chunk = [];
        };

        foreach ($provider->fetchListings() as $dto) {
            $chunk[] = $dto;
            $cursor = $this->maxCursor($cursor, $dto);
            if (count($chunk) >= $size) {
                $flush();
            }
        }
        if ($chunk !== []) {
            $flush();
        }

        // Incremental syncs continue from the newest record seen by the import.
        $connection->forceFill(['last_cursor' => $cursor ?? $connection->last_cursor, 'last_synced_at' => now()])->save();
    }

    /**
     * Detects listings no longer offered by the feed and hides them.
     * Refuses to act on a suspiciously large removal (truncated feed).
     */
    private function reconcile(MlsSyncJob $job, MlsConnection $connection, MLSProviderInterface $provider, SyncStats $stats): void
    {
        $seen = [];
        foreach ($provider->fetchListings() as $dto) {
            $seen[$dto->listingKey] = true;
            $stats->fetched++;
        }

        $active = MlsListing::query()->where('mls_provider_id', $connection->mls_provider_id)->whereNull('removed_at');
        $activeCount = (clone $active)->count();
        $missing = (clone $active)->pluck('listing_key')->reject(fn ($key) => isset($seen[$key]))->values();

        $maxRatio = (float) config('mls.sync.reconcile_max_removal_ratio', 0.2);
        if ($activeCount > 0 && ($seen === [] || $missing->count() / $activeCount > $maxRatio)) {
            throw new MLSException(
                'mls_reconcile_aborted',
                "Reconciliation aborted: {$missing->count()} of {$activeCount} active listings missing from the feed exceeds the safety threshold. No data was changed.",
                409,
            );
        }

        foreach ($missing as $key) {
            $listing = (clone $active)->where('listing_key', $key)->first();
            if ($listing) {
                $listing->forceFill(['removed_at' => now()])->save();
                $this->materializer->hide($listing);
                $stats->removed++;
                $this->sync->log($job, 'info', 'Listing no longer in the feed; hidden.', $key);
            }
        }
    }

    /**
     * @param  list<string>  $listingKeys
     */
    private function refreshMedia(MlsSyncJob $job, MlsConnection $connection, MLSProviderInterface $provider, array $listingKeys, SyncStats $stats): void
    {
        if (! $provider->capabilities()->supportsMedia) {
            return;
        }

        foreach ($listingKeys as $key) {
            $listing = MlsListing::query()->where('mls_provider_id', $connection->mls_provider_id)->where('listing_key', $key)->first();
            try {
                $stats->media += $listing ? $this->media->sync($connection, $provider, $listing) : 0;
            } catch (MLSException $e) {
                if ($e->errorCode === 'mls_rate_limited') {
                    throw $e;
                }
                $stats->errors++;
                $this->sync->log($job, 'warning', 'Media sync failed: '.$e->getMessage(), $key);
            }
        }
    }

    private function maxCursor(?string $current, MLSListingDTO $dto): ?string
    {
        if ($dto->modifiedAt === null) {
            return $current;
        }
        $candidate = $dto->modifiedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z').'|'.$dto->listingKey;

        return $current === null || strcmp($candidate, $current) > 0 ? $candidate : $current;
    }
}
