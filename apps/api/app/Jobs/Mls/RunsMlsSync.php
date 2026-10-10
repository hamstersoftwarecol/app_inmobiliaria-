<?php

namespace App\Jobs\Mls;

use App\Application\Mls\MLSSyncRunner;
use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsSyncJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Shared behaviour of the MLS queue jobs: one job per connection+type at a
 * time, client-side rate limits release the job instead of failing it.
 */
abstract class RunsMlsSync implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public function __construct(public MlsSyncJob $syncJob)
    {
        $this->onQueue('mls-sync');
    }

    public function uniqueId(): string
    {
        return $this->syncJob->mls_connection_id.':'.$this->syncJob->type;
    }

    public function handle(MLSSyncRunner $runner): void
    {
        try {
            $runner->run($this->syncJob);
        } catch (MLSException $e) {
            if ($e->errorCode === 'mls_rate_limited') {
                $this->release((int) ($e->details['retry_after'] ?? 60));

                return;
            }
            throw $e;
        }
    }
}
