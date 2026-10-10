<?php

namespace App\Application\Mls;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Jobs\Mls\ImportMLSListings;
use App\Jobs\Mls\ReconcileMLSListings;
use App\Jobs\Mls\SyncMLSListings;
use App\Models\MlsConnection;
use App\Models\MlsSyncJob;
use App\Models\User;

/**
 * Creates a sync job record and queues it. The licence gate is checked up
 * front (resolver) so unauthorized syncs are refused immediately, and an
 * already queued/running job of the same type is returned instead of
 * starting a duplicate (idempotent).
 */
class MLSSyncDispatcher
{
    public function __construct(
        private readonly MLSConnectionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function dispatch(MlsConnection $connection, string $type, ?User $actor = null): MlsSyncJob
    {
        $this->resolver->resolve($connection);

        $existing = MlsSyncJob::query()
            ->where('mls_connection_id', $connection->id)
            ->where('type', $type)
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        $job = MlsSyncJob::query()->create([
            'mls_connection_id' => $connection->id,
            'type' => $type,
            'triggered_by' => $actor?->id,
        ]);

        match ($type) {
            'import' => ImportMLSListings::dispatch($job),
            'reconcile' => ReconcileMLSListings::dispatch($job),
            default => SyncMLSListings::dispatch($job),
        };

        if ($actor) {
            $this->audit->log(AuditCategory::Mls, 'mls.sync.requested', $actor, $job, new: ['type' => $type, 'connection_id' => $connection->id]);
        }

        return $job->refresh();
    }
}
