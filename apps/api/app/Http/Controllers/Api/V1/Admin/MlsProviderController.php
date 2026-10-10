<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Domain\Mls\Enums\ProviderStatus;
use App\Domain\Mls\Exceptions\MLSException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMlsConnectionRequest;
use App\Http\Requests\Admin\StoreMlsProviderRequest;
use App\Http\Requests\Admin\UpdateMlsProviderRequest;
use App\Http\Resources\MlsProviderResource;
use App\Models\MlsConnection;
use App\Models\MlsProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Provider catalogue and encrypted connections. Providers are created
 * disabled with status "pending_authorization"; authorizing one requires an
 * explicit licence confirmation and is audited.
 */
class MlsProviderController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        return MlsProviderResource::collection(
            MlsProvider::query()->with(['displayRule', 'connections'])->orderBy('name')->get(),
        );
    }

    public function show(MlsProvider $provider): MlsProviderResource
    {
        return new MlsProviderResource($provider->load(['displayRule', 'connections']));
    }

    public function store(StoreMlsProviderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $provider = DB::transaction(function () use ($data) {
            $provider = MlsProvider::create(collect($data)->except('display_rule')->all());
            $provider->displayRule()->create($data['display_rule'] ?? []);

            return $provider;
        });

        $this->audit->log(AuditCategory::Mls, 'mls.provider.created', $request->user(), $provider, new: $provider->only(['code', 'driver', 'protocol']));

        return (new MlsProviderResource($provider->load(['displayRule', 'connections'])))->response()->setStatusCode(201);
    }

    public function update(UpdateMlsProviderRequest $request, MlsProvider $provider): MlsProviderResource
    {
        $data = $request->validated();
        $before = $provider->only(['name', 'protocol', 'status', 'is_enabled']);

        DB::transaction(function () use ($provider, $data) {
            $provider->fill(collect($data)->only(['name', 'protocol', 'website', 'notes'])->all());
            if (array_key_exists('status', $data)) {
                $provider->status = ProviderStatus::from($data['status']);
            }
            if (array_key_exists('is_enabled', $data)) {
                $provider->is_enabled = (bool) $data['is_enabled'];
            }
            // Enabling is only meaningful for an authorized provider.
            if ($provider->is_enabled && $provider->status !== ProviderStatus::Authorized) {
                throw MLSException::notAuthorized($provider->code);
            }
            $provider->save();

            if (isset($data['display_rule'])) {
                $provider->displayRule()->updateOrCreate([], $data['display_rule']);
            }
        });

        $this->audit->log(AuditCategory::Mls, 'mls.provider.updated', $request->user(), $provider, $before,
            $provider->only(['name', 'protocol', 'status', 'is_enabled']) + array_filter([
                'licence_reference' => $data['licence_reference'] ?? null,
                'display_rule_changed' => isset($data['display_rule']) ? true : null,
            ]));

        return new MlsProviderResource($provider->refresh()->load(['displayRule', 'connections']));
    }

    public function storeConnection(StoreMlsConnectionRequest $request, MlsProvider $provider): JsonResponse
    {
        $data = $request->validated();
        $data['credentials'] = array_filter($data['credentials'], fn ($v) => $v !== null && $v !== '');

        $connection = new MlsConnection($data);
        $connection->provider()->associate($provider);
        $connection->created_by = $request->user()->getKey();
        $connection->status = 'inactive';
        $connection->save();

        $this->audit->log(AuditCategory::Mls, 'mls.connection.created', $request->user(), $connection, new: [
            'provider' => $provider->code,
            'environment' => $connection->environment,
            'base_url' => $connection->base_url,
            'credential_keys' => $connection->credentialKeys(),
        ]);

        return (new MlsProviderResource($provider->load(['displayRule', 'connections'])))->response()->setStatusCode(201);
    }

    /**
     * Performs only the authentication handshake — no data is imported.
     */
    public function testConnection(MlsConnection $connection, MLSConnectionResolver $resolver): JsonResponse
    {
        try {
            $resolver->resolve($connection)->authenticate();
            $connection->forceFill(['status' => 'verified', 'last_error' => null])->save();
            $ok = true;
        } catch (MLSException $e) {
            $connection->forceFill(['status' => 'error', 'last_error' => $e->getMessage()])->save();
            $ok = false;
            $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        }

        $this->audit->log(AuditCategory::Mls, 'mls.connection.tested', subject: $connection, metadata: ['success' => $ok]);

        return response()->json(['success' => $ok, 'error' => $error ?? null], $ok ? 200 : 422);
    }
}
