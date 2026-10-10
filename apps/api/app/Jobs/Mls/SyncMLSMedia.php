<?php

namespace App\Jobs\Mls;

use App\Application\Mls\MLSMediaService;
use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsConnection;
use App\Models\MlsListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Refreshes the photos of one listing on demand (e.g. from the admin panel).
 * Regular syncs refresh media for every created/updated listing inline.
 */
class SyncMLSMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public MlsConnection $connection, public MlsListing $listing)
    {
        $this->onQueue('mls-media');
    }

    public function handle(MLSConnectionResolver $resolver, MLSMediaService $media): void
    {
        try {
            $media->sync($this->connection, $resolver->resolve($this->connection), $this->listing);
        } catch (MLSException $e) {
            if ($e->errorCode === 'mls_rate_limited') {
                $this->release((int) ($e->details['retry_after'] ?? 60));

                return;
            }
            throw $e;
        }
    }
}
