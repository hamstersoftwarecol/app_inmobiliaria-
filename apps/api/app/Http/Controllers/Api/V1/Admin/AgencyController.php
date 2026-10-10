<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\StoreAgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgencyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        return AgencyResource::collection(Agency::query()->withCount(['offices', 'agents'])->orderBy('name')->get());
    }

    public function show(Agency $agency): AgencyResource
    {
        return new AgencyResource($agency->load(['offices' => fn ($q) => $q->withCount('agents')->orderByDesc('is_headquarters')->orderBy('name')])->loadCount(['offices', 'agents']));
    }

    public function store(StoreAgencyRequest $request): JsonResponse
    {
        $agency = new Agency($request->validated());
        $agency->slug = UniqueSlug::for($agency->name, Agency::query());
        $agency->save();

        $this->audit->log(AuditCategory::Admin, 'agencies.created', $request->user(), $agency, new: $agency->only(['name', 'license_number']));

        return (new AgencyResource($agency))->response()->setStatusCode(201);
    }

    public function update(StoreAgencyRequest $request, Agency $agency): AgencyResource
    {
        $agency->fill($request->validated());
        $changes = $agency->getDirty();
        $original = array_intersect_key($agency->getOriginal(), $changes);
        $agency->save();

        if ($changes !== []) {
            $this->audit->log(AuditCategory::Admin, 'agencies.updated', $request->user(), $agency, $original, $changes);
        }

        return new AgencyResource($agency);
    }
}
