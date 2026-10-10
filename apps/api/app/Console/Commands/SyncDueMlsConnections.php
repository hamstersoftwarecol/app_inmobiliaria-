<?php

namespace App\Console\Commands;

use App\Application\Mls\MLSSyncDispatcher;
use App\Domain\Mls\Enums\ProviderStatus;
use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsConnection;
use Illuminate\Console\Command;

/**
 * Queues incremental syncs for production connections whose provider is
 * authorized and whose refresh interval (display rules) has elapsed.
 * Does nothing while MLS_SYNC_ENABLED=false.
 */
class SyncDueMlsConnections extends Command
{
    protected $signature = 'hitlease:mls:sync-due {--type=incremental : incremental or reconcile}';

    protected $description = 'Queue MLS syncs for authorized production connections that are due';

    public function handle(MLSSyncDispatcher $dispatcher): int
    {
        if (! config('hitlease.mls.sync_enabled')) {
            $this->info('MLS sync is disabled (MLS_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        $type = $this->option('type') === 'reconcile' ? 'reconcile' : 'incremental';
        $queued = 0;

        MlsConnection::query()
            ->where('environment', 'production')
            ->whereHas('provider', fn ($q) => $q->where('is_enabled', true)->where('status', ProviderStatus::Authorized->value))
            ->with('provider.displayRule')
            ->each(function (MlsConnection $connection) use ($dispatcher, $type, &$queued) {
                $interval = (int) ($connection->provider->displayRule?->min_refresh_minutes ?? 720);
                $due = $type === 'reconcile' || $connection->last_synced_at === null || $connection->last_synced_at->addMinutes($interval)->isPast();
                if (! $due) {
                    return;
                }

                try {
                    $dispatcher->dispatch($connection, $type);
                    $queued++;
                } catch (MLSException $e) {
                    $this->warn("Connection {$connection->id}: {$e->getMessage()}");
                }
            });

        $this->info("Queued {$queued} {$type} sync(s).");

        return self::SUCCESS;
    }
}
