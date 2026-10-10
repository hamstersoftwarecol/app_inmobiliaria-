<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Domain\Listings\Enums\ListingType;
use App\Domain\Listings\Enums\PropertyTypeCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPropertyResource;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Public listings. Full search (map bounds, radius, polygons, saved
 * searches) arrives in Sprint 5; this endpoint offers basic browsing.
 * Mobile clients identify themselves with `X-Hitlease-Client: mobile` so
 * MLS rules that forbid mobile display are honoured.
 */
class PropertyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'listing_type' => ['nullable', Rule::enum(ListingType::class)],
            'property_type' => ['nullable', Rule::enum(PropertyTypeCode::class)],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'min_beds' => ['nullable', 'integer', 'min:0', 'max:20'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $sort = $f['sort'] ?? 'newest';

        $properties = Property::query()
            ->publiclyVisible($this->channel($request))
            ->with(['propertyType', 'media', 'mlsListing.provider.displayRule'])
            ->when($f['listing_type'] ?? null, fn ($q, $v) => $q->where('listing_type', $v))
            ->when($f['property_type'] ?? null, fn ($q, $v) => $q->whereHas('propertyType', fn ($t) => $t->where('code', $v)))
            ->when($f['city'] ?? null, fn ($q, $v) => $q->whereRaw('lower(city) = ?', [mb_strtolower($v)]))
            ->when($f['postal_code'] ?? null, fn ($q, $v) => $q->where('postal_code', $v))
            ->when(isset($f['min_price']), fn ($q) => $q->where('list_price', '>=', $f['min_price']))
            ->when(isset($f['max_price']), fn ($q) => $q->where('list_price', '<=', $f['max_price']))
            ->when(isset($f['min_beds']), fn ($q) => $q->where('bedrooms', '>=', $f['min_beds']))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('list_price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('list_price'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($f['per_page'] ?? 24)
            ->withQueryString();

        return PublicPropertyResource::collection($properties->through(fn (Property $p) => (new PublicPropertyResource($p))->summary()));
    }

    public function show(Request $request, string $slug): PublicPropertyResource
    {
        $property = Property::query()
            ->publiclyVisible($this->channel($request))
            ->where('slug', $slug)
            ->with(['propertyType', 'media', 'agency', 'listingAgent.user', 'mlsListing.provider.displayRule'])
            ->firstOrFail();

        return new PublicPropertyResource($property);
    }

    public function types(): JsonResponse
    {
        return response()->json(['data' => PropertyType::query()->orderBy('sort_order')->get(['id', 'code', 'name_en', 'name_es'])]);
    }

    private function channel(Request $request): string
    {
        return strtolower((string) $request->header('X-Hitlease-Client')) === 'mobile' ? 'mobile' : 'web';
    }
}
