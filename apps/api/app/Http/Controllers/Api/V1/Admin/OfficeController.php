<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Audit\AuditLogger;
use App\Domain\Agency\Enums\AgentStatus;
use App\Domain\Agency\Exceptions\AgencyException;
use App\Domain\Audit\AuditCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\StoreOfficeRequest;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OfficeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);

        return OfficeResource::collection(
            Office::query()
                ->withCount('agents')
                ->when($filters['agency_id'] ?? null, fn ($q, $id) => $q->where('agency_id', $id))
                ->when(array_key_exists('active', $filters), fn ($q) => $q->where('is_active', $request->boolean('active')))
                ->orderByDesc('is_headquarters')->orderBy('name')
                ->get(),
        );
    }

    public function store(StoreOfficeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $office = DB::transaction(function () use ($data) {
            $office = new Office(collect($data)->except('agency_id')->all());
            $office->agency_id = $data['agency_id'];
            $office->slug = UniqueSlug::for($office->name, Office::query()->where('agency_id', $office->agency_id));
            $this->demoteOtherHeadquarters($office);
            $office->save();

            return $office;
        });

        $this->audit->log(AuditCategory::Admin, 'offices.created', $request->user(), $office, new: $office->only(['agency_id', 'name', 'city']));

        return (new OfficeResource($office->refresh()))->response()->setStatusCode(201);
    }

    public function update(StoreOfficeRequest $request, Office $office): OfficeResource
    {
        DB::transaction(function () use ($request, $office) {
            $office->fill($request->validated());
            $this->demoteOtherHeadquarters($office);
            $changes = $office->getDirty();
            $original = array_intersect_key($office->getOriginal(), $changes);
            $office->save();

            if ($changes !== []) {
                $this->audit->log(AuditCategory::Admin, 'offices.updated', $request->user(), $office, $original, $changes);
            }
        });

        return new OfficeResource($office->refresh()->loadCount('agents'));
    }

    public function destroy(Request $request, Office $office): JsonResponse
    {
        if ($office->agents()->where('status', AgentStatus::Active->value)->exists()) {
            throw AgencyException::officeHasAgents();
        }

        $office->agents()->update(['office_id' => null]);
        $office->delete();
        $this->audit->log(AuditCategory::Admin, 'offices.deleted', $request->user(), $office);

        return response()->json(null, 204);
    }

    private function demoteOtherHeadquarters(Office $office): void
    {
        if ($office->is_headquarters) {
            Office::query()->where('agency_id', $office->agency_id)
                ->when($office->exists, fn ($q) => $q->whereKeyNot($office->id))
                ->where('is_headquarters', true)
                ->update(['is_headquarters' => false]);
        }
    }
}
