<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Agency\LicenseVerificationService;
use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Agency\Enums\VerificationSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\ReviewLicenseRequest;
use App\Http\Resources\AgentLicenseResource;
use App\Models\AgentLicense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Licence verification queue for brokers/super admins.
 */
class LicenseController extends Controller
{
    public function __construct(private readonly LicenseVerificationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AgentLicense::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(LicenseStatus::class)],
            'expiring_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $licenses = AgentLicense::query()
            ->with(['agent.user', 'verifier'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['expiring_within_days'] ?? null, fn ($q, $days) => $q
                ->where('status', LicenseStatus::Verified->value)
                ->whereBetween('expires_on', [today()->toDateString(), today()->addDays($days)->toDateString()]))
            ->orderBy('created_at')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return AgentLicenseResource::collection($licenses);
    }

    public function verify(ReviewLicenseRequest $request, AgentLicense $license): AgentLicenseResource
    {
        Gate::authorize('review', $license);

        $data = $request->validated();
        $this->service->verify(
            $request->user(),
            $license,
            VerificationSource::from($data['verification_source']),
            $data['verification_reference'],
            $data['expires_on'],
            $data['notes'] ?? null,
        );

        return new AgentLicenseResource($license->refresh()->load(['agent.user', 'verifier']));
    }

    public function reject(ReviewLicenseRequest $request, AgentLicense $license): AgentLicenseResource
    {
        Gate::authorize('review', $license);
        $this->service->reject($request->user(), $license, $request->validated('reason'));

        return new AgentLicenseResource($license->refresh()->load(['agent.user', 'verifier']));
    }

    public function revoke(ReviewLicenseRequest $request, AgentLicense $license): AgentLicenseResource
    {
        Gate::authorize('review', $license);
        $this->service->revoke($request->user(), $license, $request->validated('reason'));

        return new AgentLicenseResource($license->refresh()->load(['agent.user', 'verifier']));
    }
}
