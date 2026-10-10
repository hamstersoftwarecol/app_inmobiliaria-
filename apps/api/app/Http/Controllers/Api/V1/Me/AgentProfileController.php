<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Agency\AgentProfileService;
use App\Application\Agency\LicenseVerificationService;
use App\Domain\Agency\Exceptions\AgencyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\AgentProfileRequest;
use App\Http\Requests\Agency\SubmitLicenseRequest;
use App\Http\Resources\AgentLicenseResource;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Agent self-service: edit public profile fields and submit licences for
 * verification. Status, office and agency are managed by staff.
 */
class AgentProfileController extends Controller
{
    public function show(Request $request): AgentResource
    {
        $agent = $this->ownProfile($request);
        Gate::authorize('view', $agent);

        return new AgentResource($agent->load(['user', 'agency', 'office', 'licenses', 'verifiedLicense']));
    }

    public function update(AgentProfileRequest $request, AgentProfileService $profiles): AgentResource
    {
        $agent = $this->ownProfile($request);
        Gate::authorize('updateOwn', $agent);

        $profiles->update($request->user(), $agent, collect($request->validated())->only(AgentProfileService::SELF_EDITABLE)->all());

        return new AgentResource($agent->refresh()->load(['user', 'agency', 'office', 'licenses', 'verifiedLicense']));
    }

    public function storeLicense(SubmitLicenseRequest $request, LicenseVerificationService $licenses): JsonResponse
    {
        $agent = $this->ownProfile($request);
        Gate::authorize('submitLicense', $agent);

        return (new AgentLicenseResource($licenses->submit($request->user(), $agent, $request->validated())))
            ->response()->setStatusCode(201);
    }

    private function ownProfile(Request $request): Agent
    {
        return $request->user()->agentProfile()->first() ?? throw AgencyException::agentProfileMissing();
    }
}
