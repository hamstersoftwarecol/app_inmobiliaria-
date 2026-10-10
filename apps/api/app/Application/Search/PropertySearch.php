<?php

namespace App\Application\Search;

use App\Domain\Listings\Enums\ListingType;
use App\Domain\Listings\Enums\PropertyTypeCode;
use App\Http\Requests\Listings\PropertyRequest;
use App\Models\Property;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Public listing search shared by the search endpoint and saved-search alerts,
 * so an alert can never match a listing the search page would not show.
 *
 * Geographic filters (bbox, radius, polygon) only match listings whose exact
 * location is public (`address_display`), otherwise narrowing the area would
 * reveal a suppressed address.
 */
final class PropertySearch
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'sqft_desc', 'distance'];

    public const MAX_RADIUS_MILES = 50;

    public const MAX_POLYGON_POINTS = 50;

    /** Filter keys persisted by saved searches (pagination and sort excluded). */
    public const FILTER_KEYS = [
        'listing_type', 'property_type', 'city', 'county', 'postal_code', 'min_price', 'max_price', 'min_beds',
        'min_baths', 'min_sqft', 'max_sqft', 'min_year', 'amenities', 'bbox', 'lat', 'lng', 'radius_miles', 'polygon',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'listing_type' => ['nullable', Rule::enum(ListingType::class)],
            'property_type' => ['nullable', Rule::enum(PropertyTypeCode::class)],
            'city' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'gte:min_price'],
            'min_beds' => ['nullable', 'integer', 'min:0', 'max:20'],
            'min_baths' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'min_sqft' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_sqft' => ['nullable', 'integer', 'min:0', 'max:1000000', 'gte:min_sqft'],
            'min_year' => ['nullable', 'integer', 'min:1600', 'max:2100'],
            'amenities' => ['nullable', 'array', 'max:10'],
            'amenities.*' => ['string', 'distinct', Rule::in(PropertyRequest::AMENITIES)],
            'bbox' => ['nullable', 'string', 'max:120', self::bboxRule(), 'prohibits:polygon,radius_miles'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:radius_miles,lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:radius_miles,lat'],
            'radius_miles' => ['nullable', 'numeric', 'gt:0', 'max:'.self::MAX_RADIUS_MILES, 'required_with:lat', 'prohibits:polygon'],
            'polygon' => ['nullable', 'string', 'max:2000', self::polygonRule()],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ];
    }

    /**
     * Keeps only non-empty filter values (used to store saved searches).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function filtersFrom(array $validated): array
    {
        return array_filter(
            Arr::only($validated, self::FILTER_KEYS),
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }

    /**
     * @param  Builder<Property>  $query
     * @param  array<string, mixed>  $f
     * @return Builder<Property>
     */
    public function apply(Builder $query, array $f, ?string $sort = null): Builder
    {
        $num = fn (string $key) => isset($f[$key]) && $f[$key] !== '' ? $f[$key] : null;

        $query
            ->when($f['listing_type'] ?? null, fn ($q, $v) => $q->where('listing_type', $v))
            ->when($f['property_type'] ?? null, fn ($q, $v) => $q->whereHas('propertyType', fn ($t) => $t->where('code', $v)))
            ->when($f['city'] ?? null, fn ($q, $v) => $q->whereRaw('lower(properties.city) = ?', [mb_strtolower(trim($v))]))
            ->when($f['county'] ?? null, fn ($q, $v) => $q->whereRaw('lower(properties.county) = ?', [mb_strtolower(trim($v))]))
            ->when($f['postal_code'] ?? null, fn ($q, $v) => $q->where('postal_code', 'like', $v.'%'))
            ->when($num('min_price') !== null, fn ($q) => $q->where('list_price', '>=', $f['min_price']))
            ->when($num('max_price') !== null, fn ($q) => $q->where('list_price', '<=', $f['max_price']))
            ->when($num('min_beds') !== null, fn ($q) => $q->where('bedrooms', '>=', $f['min_beds']))
            ->when($num('min_baths') !== null, fn ($q) => $q->where('bathrooms', '>=', $f['min_baths']))
            ->when($num('min_sqft') !== null, fn ($q) => $q->where('living_area_sqft', '>=', $f['min_sqft']))
            ->when($num('max_sqft') !== null, fn ($q) => $q->where('living_area_sqft', '<=', $f['max_sqft']))
            ->when($num('min_year') !== null, fn ($q) => $q->where('year_built', '>=', $f['min_year']))
            ->when($f['amenities'] ?? null, fn ($q, $v) => $q->whereRaw('properties.amenities @> ?::jsonb', [json_encode(array_values($v))]));

        $hasRadius = isset($f['lat'], $f['lng'], $f['radius_miles']);
        if (! empty($f['bbox']) || $hasRadius || ! empty($f['polygon'])) {
            $query->where('properties.address_display', true)->whereNotNull('properties.location');
        }
        if (! empty($f['bbox'])) {
            [$west, $south, $east, $north] = self::parseBbox($f['bbox']);
            $query->whereRaw('ST_Intersects(properties.location, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)', [$west, $south, $east, $north]);
        }
        if ($hasRadius) {
            $query->whereRaw(
                'ST_DWithin(properties.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                [(float) $f['lng'], (float) $f['lat'], (float) $f['radius_miles'] * 1609.344],
            );
        }
        if (! empty($f['polygon'])) {
            $query->whereRaw('ST_Intersects(properties.location, ST_GeogFromText(?))', [self::polygonWkt($f['polygon'])]);
        }

        match ($sort ?? 'newest') {
            'price_asc' => $query->orderByRaw('list_price asc nulls last'),
            'price_desc' => $query->orderByRaw('list_price desc nulls last'),
            'sqft_desc' => $query->orderByRaw('living_area_sqft desc nulls last'),
            'distance' => $hasRadius
                ? $query->orderByRaw('ST_Distance(properties.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)', [(float) $f['lng'], (float) $f['lat']])
                : null,
            default => null,
        };

        return $query->orderByDesc('properties.published_at')->orderByDesc('properties.id');
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}|null west, south, east, north
     */
    public static function parseBbox(string $value): ?array
    {
        $parts = explode(',', $value);
        if (count($parts) !== 4 || array_filter($parts, fn ($p) => ! is_numeric(trim($p)))) {
            return null;
        }
        [$w, $s, $e, $n] = array_map(fn ($p) => (float) $p, $parts);
        if ($w < -180 || $e > 180 || $s < -90 || $n > 90 || $w >= $e || $s >= $n) {
            return null;
        }

        return [$w, $s, $e, $n];
    }

    /**
     * Parses "lng lat,lng lat,..." into a closed ring, or null when invalid.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public static function parsePolygon(string $value): ?array
    {
        $points = [];
        foreach (explode(',', trim($value)) as $pair) {
            $xy = preg_split('/\s+/', trim($pair));
            if (count($xy) !== 2 || ! is_numeric($xy[0]) || ! is_numeric($xy[1])) {
                return null;
            }
            [$lng, $lat] = [(float) $xy[0], (float) $xy[1]];
            if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                return null;
            }
            $points[] = [$lng, $lat];
        }
        if ($points !== [] && $points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        return count($points) >= 4 && count($points) <= self::MAX_POLYGON_POINTS + 1 ? $points : null;
    }

    private static function polygonWkt(string $value): string
    {
        $ring = array_map(fn (array $p) => sprintf('%.7F %.7F', $p[0], $p[1]), self::parsePolygon($value) ?? []);

        return 'SRID=4326;POLYGON(('.implode(',', $ring).'))';
    }

    private static function bboxRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || self::parseBbox($value) === null) {
                $fail('The bbox must be "west,south,east,north" in degrees.');
            }
        };
    }

    private static function polygonRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || self::parsePolygon($value) === null) {
                $fail('The polygon must be 3 to '.self::MAX_POLYGON_POINTS.' "lng lat" points separated by commas.');
            }
        };
    }
}
