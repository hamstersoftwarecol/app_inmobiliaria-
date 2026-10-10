<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\AgentProfileRequest;
use App\Http\Resources\PublicAgentResource;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Public agent directory: only active, opted-in agents with a verified,
 * unexpired licence are visible.
 */
class AgentDirectoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', Rule::in(AgentProfileRequest::LANGUAGES)],
            'specialty' => ['nullable', Rule::in(AgentProfileRequest::SPECIALTIES)],
            'city' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $agents = Agent::query()
            ->publiclyListed()
            ->with(['user', 'agency', 'office', 'verifiedLicense'])
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $q->whereHas('user', fn ($u) => $u->whereRaw("lower(first_name || ' ' || last_name) like ?", [$term]));
            })
            ->when($filters['language'] ?? null, fn ($q, $v) => $q->whereJsonContains('languages', $v))
            ->when($filters['specialty'] ?? null, fn ($q, $v) => $q->whereJsonContains('specialties', $v))
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->whereHas('office', fn ($o) => $o->whereRaw('lower(city) = ?', [mb_strtolower($v)])))
            ->join('users', 'users.id', '=', 'agents.user_id')
            ->orderBy('users.last_name')->orderBy('users.first_name')
            ->select('agents.*')
            ->paginate($filters['per_page'] ?? 24)
            ->withQueryString();

        return PublicAgentResource::collection($agents);
    }

    public function show(string $slug): PublicAgentResource
    {
        $agent = Agent::query()->publiclyListed()->where('slug', $slug)
            ->with(['user', 'agency', 'office', 'verifiedLicense'])
            ->firstOrFail();

        return new PublicAgentResource($agent);
    }
}
