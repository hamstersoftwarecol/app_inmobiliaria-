<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Application\Listings\PropertyMediaService;
use App\Application\Listings\PropertyService;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Listings\Enums\PropertySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\PropertyMediaRequest;
use App\Http\Requests\Listings\PropertyRequest;
use App\Http\Resources\PropertyMediaResource;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\PropertyMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Staff listing management. Agents only see and edit listings where they
 * are the listing agent; brokers/office managers see all.
 */
class PropertyController extends Controller
{
    private const RELATIONS = ['propertyType', 'listingAgent.user', 'media', 'mlsListing.provider', 'statusHistory'];

    public function __construct(
        private readonly PropertyService $properties,
        private readonly PropertyMediaService $media,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Property::class);

        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::enum(PropertySource::class)],
            'published' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:30'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();
        $seeAll = $user->isSuperAdmin() || $user->hasPermission(Permission::PropertiesViewAny);

        $list = Property::query()
            ->with(['propertyType', 'listingAgent.user', 'media'])
            ->when(! $seeAll, fn ($q) => $q->whereHas('listingAgent', fn ($a) => $a->where('user_id', $user->id)))
            ->when($f['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $q->where(fn ($w) => $w->whereRaw('lower(title) like ?', [$term])
                    ->orWhereRaw('lower(street_address) like ?', [$term])
                    ->orWhereRaw('lower(city) like ?', [$term])
                    ->orWhere('postal_code', $search));
            })
            ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when(array_key_exists('published', $f), fn ($q) => $q->where('is_published', $request->boolean('published')))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('updated_at')
            ->paginate($f['per_page'] ?? 20)
            ->withQueryString();

        return PropertyResource::collection($list);
    }

    public function show(Property $property): PropertyResource
    {
        Gate::authorize('view', $property);

        return new PropertyResource($property->load(self::RELATIONS));
    }

    public function store(PropertyRequest $request): JsonResponse
    {
        Gate::authorize('create', Property::class);

        $property = $this->properties->create($request->user(), $request->validatedForModel());

        return (new PropertyResource($property->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function update(PropertyRequest $request, Property $property): PropertyResource
    {
        Gate::authorize('update', $property);

        return new PropertyResource($this->properties->update($request->user(), $property, $request->validatedForModel())->load(self::RELATIONS));
    }

    public function publish(Request $request, Property $property): PropertyResource
    {
        Gate::authorize('publish', $property);

        return new PropertyResource($this->properties->publish($request->user(), $property)->load(self::RELATIONS));
    }

    public function unpublish(Request $request, Property $property): PropertyResource
    {
        Gate::authorize('publish', $property);

        return new PropertyResource($this->properties->unpublish($request->user(), $property)->load(self::RELATIONS));
    }

    public function destroy(Request $request, Property $property): JsonResponse
    {
        Gate::authorize('delete', $property);
        $this->properties->delete($request->user(), $property);

        return response()->json(null, 204);
    }

    public function storeMedia(PropertyMediaRequest $request, Property $property): JsonResponse
    {
        Gate::authorize('update', $property);

        $media = $this->media->upload($request->user(), $property, $request->file('photo'), $request->validated('caption'));

        return (new PropertyMediaResource($media))->response()->setStatusCode(201);
    }

    public function reorderMedia(Request $request, Property $property): AnonymousResourceCollection
    {
        Gate::authorize('update', $property);
        $ids = $request->validate([
            'order' => ['required', 'array', 'max:100'],
            'order.*' => ['integer', 'distinct', Rule::exists('property_media', 'id')->where('property_id', $property->id)],
        ])['order'];

        $this->media->reorder($property, $ids);

        return PropertyMediaResource::collection($property->media()->get());
    }

    public function destroyMedia(Request $request, Property $property, PropertyMedia $media): JsonResponse
    {
        Gate::authorize('update', $property);
        abort_unless($media->property_id === $property->id, 404);

        $this->media->delete($request->user(), $media);

        return response()->json(null, 204);
    }
}
