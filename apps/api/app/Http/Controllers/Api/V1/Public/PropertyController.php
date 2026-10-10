<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Application\Search\PropertySearch;
use App\Http\Controllers\Concerns\ResolvesClientChannel;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPropertyResource;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public listing search (filters, map bounds, radius, polygon) and location
 * autocomplete. Mobile clients identify themselves with `X-App-Inmobiliaria-Client: mobile` so
 * MLS rules that forbid mobile display are honoured.
 */
class PropertyController extends Controller
{
    use ResolvesClientChannel;

    public function index(Request $request, PropertySearch $search): AnonymousResourceCollection
    {
        $f = $request->validate([
            ...PropertySearch::rules(),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $query = Property::query()
            ->publiclyVisible($this->channel($request))
            ->with(['propertyType', 'media', 'mlsListing.provider.displayRule']);

        $properties = $search->apply($query, $f, $f['sort'] ?? null)
            ->paginate($f['per_page'] ?? 24)
            ->withQueryString();

        return PublicPropertyResource::collection($properties->through(fn (Property $p) => (new PublicPropertyResource($p))->summary()));
    }

    /**
     * Autocomplete for cities and ZIP codes that currently have public listings. The map
     * centre is computed only from listings whose exact location is public.
     */
    public function locations(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']])['q']);
        $base = fn () => Property::query()->publiclyVisible($this->channel($request))->toBase();
        $center = 'ST_Y(ST_Centroid(ST_Collect(properties.location::geometry) FILTER (WHERE properties.address_display))) as lat, '
            .'ST_X(ST_Centroid(ST_Collect(properties.location::geometry) FILTER (WHERE properties.address_display))) as lng';

        $rows = collect();
        if (preg_match('/^\d{2,5}$/', $q)) {
            $rows = $base()->where('postal_code', 'like', $q.'%')
                ->selectRaw("'postal_code' as type, postal_code, min(city) as city, max(county) as county, state, count(*) as listings, {$center}")
                ->groupBy('postal_code', 'state')->orderByDesc('listings')->limit(8)->get();
        } else {
            $term = addcslashes(mb_strtolower($q), '%_\\').'%';
            $rows = $base()->whereRaw('lower(properties.city) like ?', [$term])
                ->selectRaw("'city' as type, null as postal_code, min(city) as city, max(county) as county, state, count(*) as listings, {$center}")
                ->groupByRaw('lower(properties.city), state')->orderByDesc('listings')->limit(8)->get();
        }

        return response()->json(['data' => $rows->map(fn ($r) => [
            'type' => $r->type,
            'label' => $r->type === 'postal_code' ? "{$r->postal_code} · {$r->city}, {$r->state}" : "{$r->city}, {$r->state}",
            'city' => $r->city,
            'county' => $r->county,
            'state' => $r->state,
            'postal_code' => $r->postal_code,
            'listings' => (int) $r->listings,
            'center' => $r->lat !== null ? ['latitude' => round((float) $r->lat, 5), 'longitude' => round((float) $r->lng, 5)] : null,
        ])->values()]);
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
}
