<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Agency\AgentProfileService;
use App\Application\Agency\LicenseVerificationService;
use App\Domain\Agency\Enums\AgentStatus;
use App\Domain\Agency\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\AgentProfileRequest;
use App\Http\Requests\Agency\SubmitLicenseRequest;
use App\Http\Resources\AgentLicenseResource;
use App\Http\Resources\AgentResource;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function __construct(private readonly AgentProfileService $profiles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Agent::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AgentStatus::class)],
            'office_id' => ['nullable', 'integer'],
            'agency_id' => ['nullable', 'integer'],
            'license' => ['nullable', Rule::in(['verified', 'pending', 'none'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $validVerified = fn ($q) => $q->where('status', LicenseStatus::Verified->value)
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today()->toDateString()));

        $agents = Agent::query()
            ->with(['user', 'agency', 'office', 'verifiedLicense'])
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $q->whereHas('user', fn ($u) => $u->whereRaw('lower(email) like ?', [$term])
                    ->orWhereRaw("lower(first_name || ' ' || last_name) like ?", [$term]));
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['office_id'] ?? null, fn ($q, $v) => $q->where('office_id', $v))
            ->when($filters['agency_id'] ?? null, fn ($q, $v) => $q->where('agency_id', $v))
            ->when(($filters['license'] ?? null) === 'verified', fn ($q) => $q->whereHas('licenses', $validVerified))
            ->when(($filters['license'] ?? null) === 'pending', fn ($q) => $q->whereHas('licenses', fn ($l) => $l->where('status', LicenseStatus::PendingVerification->value)))
            ->when(($filters['license'] ?? null) === 'none', fn ($q) => $q->whereDoesntHave('licenses', $validVerified))
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return AgentResource::collection($agents);
    }

    public function store(AgentProfileRequest $request): JsonResponse
    {
        Gate::authorize('create', Agent::class);

        $data = $request->validated();
        $agent = $this->profiles->create(
            $request->user(),
            User::query()->findOrFail($data['user_id']),
            Agency::query()->findOrFail($data['agency_id']),
            collect($data)->except(['user_id', 'agency_id', 'status'])->all(),
        );

        return (new AgentResource($agent->load(['user', 'agency', 'office', 'licenses', 'verifiedLicense'])))
            ->response()->setStatusCode(201);
    }

    public function show(Agent $agent): AgentResource
    {
        Gate::authorize('view', $agent);

        return new AgentResource($agent->load(['user', 'agency', 'office', 'licenses.verifier', 'verifiedLicense']));
    }

    public function update(AgentProfileRequest $request, Agent $agent): AgentResource
    {
        Gate::authorize('update', $agent);

        $data = $request->validated();
        if (isset($data['status'])) {
            $this->profiles->setStatus($request->user(), $agent, AgentStatus::from($data['status']));
        }
        $this->profiles->update($request->user(), $agent, collect($data)->except('status')->all());

        return new AgentResource($agent->refresh()->load(['user', 'agency', 'office', 'licenses.verifier', 'verifiedLicense']));
    }

    public function storeLicense(SubmitLicenseRequest $request, Agent $agent, LicenseVerificationService $licenses): JsonResponse
    {
        Gate::authorize('submitLicense', $agent);

        $license = $licenses->submit($request->user(), $agent, $request->validated());

        return (new AgentLicenseResource($license))->response()->setStatusCode(201);
    }
}
