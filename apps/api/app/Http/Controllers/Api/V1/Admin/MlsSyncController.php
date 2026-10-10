<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Audit\AuditLogger;
use App\Application\Mls\MLSDeduplicationService;
use App\Application\Mls\MLSSyncDispatcher;
use App\Domain\Audit\AuditCategory;
use App\Http\Controllers\Controller;
use App\Models\MlsConnection;
use App\Models\MlsListing;
use App\Models\MlsProvider;
use App\Models\MlsSyncJob;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * MLS monitoring panel: trigger syncs, inspect jobs/logs, health per
 * connection, cross-provider duplicate candidates and field mappings.
 */
class MlsSyncController extends Controller
{
    public function sync(Request $request, MLSSyncDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'integer', Rule::exists('mls_connections', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::in(['import', 'incremental', 'reconcile'])],
        ]);

        $job = $dispatcher->dispatch(MlsConnection::query()->findOrFail($data['connection_id']), $data['type'], $request->user());

        return response()->json(['data' => $this->jobPayload($job)], 202);
    }

    public function jobs(Request $request): JsonResponse
    {
        $f = $request->validate([
            'connection_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['queued', 'running', 'succeeded', 'partial', 'failed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = MlsSyncJob::query()
            ->with('connection.provider')
            ->when($f['connection_id'] ?? null, fn ($q, $v) => $q->where('mls_connection_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate($f['per_page'] ?? 20);

        return response()->json([
            'data' => collect($page->items())->map(fn (MlsSyncJob $j) => $this->jobPayload($j)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function job(MlsSyncJob $job): JsonResponse
    {
        return response()->json(['data' => $this->jobPayload($job->load('connection.provider')) + [
            'logs' => $job->logs()->latest('id')->limit(200)->get(['level', 'message', 'listing_key', 'context', 'created_at']),
        ]]);
    }

    public function status(MLSDeduplicationService $dedup): JsonResponse
    {
        $connections = MlsConnection::query()->with(['provider.displayRule'])->orderBy('id')->get()->map(function (MlsConnection $c) {
            $refresh = (int) ($c->provider->displayRule?->min_refresh_minutes ?? 720);
            $listings = MlsListing::query()->where('mls_provider_id', $c->mls_provider_id);
            $lastJob = $c->syncJobs()->latest('id')->first();

            return [
                'connection_id' => $c->id,
                'name' => $c->name,
                'provider' => ['id' => $c->provider->id, 'code' => $c->provider->code, 'name' => $c->provider->name, 'status' => $c->provider->status->value, 'is_enabled' => $c->provider->is_enabled],
                'environment' => $c->environment,
                'status' => $c->status,
                'last_error' => $c->last_error,
                'last_synced_at' => $c->last_synced_at?->toIso8601String(),
                // Stale when twice the refresh interval passed without a sync.
                'is_stale' => $c->isProduction() && ($c->last_synced_at === null || $c->last_synced_at->addMinutes($refresh * 2)->isPast()),
                'listings' => [
                    'total' => (clone $listings)->count(),
                    'removed' => (clone $listings)->whereNotNull('removed_at')->count(),
                    'published' => Property::query()->published()->whereIn('mls_listing_id', (clone $listings)->select('id'))->count(),
                ],
                'last_job' => $lastJob ? $this->jobPayload($lastJob) : null,
            ];
        });

        return response()->json(['data' => [
            'sync_enabled' => (bool) config('hitlease.mls.sync_enabled'),
            'connections' => $connections,
            'duplicate_candidates' => $dedup->crossProviderCandidates(),
        ]]);
    }

    public function fieldMappings(MlsProvider $provider): JsonResponse
    {
        return response()->json(['data' => $provider->fieldMappings()->orderBy('internal_field')->get(['internal_field', 'provider_field'])]);
    }

    public function updateFieldMappings(Request $request, MlsProvider $provider, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'mappings' => ['present', 'array', 'max:100'],
            'mappings.*.internal_field' => ['required', 'string', 'max:60', 'regex:/^[a-z_]+$/', 'distinct'],
            'mappings.*.provider_field' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.]+$/'],
        ]);

        $before = $provider->fieldMappings()->pluck('provider_field', 'internal_field')->all();
        DB::transaction(function () use ($provider, $data) {
            $provider->fieldMappings()->delete();
            foreach ($data['mappings'] as $m) {
                $provider->fieldMappings()->create($m);
            }
        });
        $after = $provider->fieldMappings()->pluck('provider_field', 'internal_field')->all();
        $audit->log(AuditCategory::Mls, 'mls.field_mappings.updated', $request->user(), $provider, $before, $after);

        return $this->fieldMappings($provider);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(MlsSyncJob $job): array
    {
        return [
            'id' => $job->id,
            'connection_id' => $job->mls_connection_id,
            'provider' => $job->relationLoaded('connection') ? $job->connection?->provider?->code : null,
            'type' => $job->type,
            'status' => $job->status,
            'stats' => $job->stats,
            'error' => $job->error,
            'cursor_before' => $job->cursor_before,
            'cursor_after' => $job->cursor_after,
            'triggered_by' => $job->triggered_by,
            'started_at' => $job->started_at?->toIso8601String(),
            'finished_at' => $job->finished_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
        ];
    }
}
