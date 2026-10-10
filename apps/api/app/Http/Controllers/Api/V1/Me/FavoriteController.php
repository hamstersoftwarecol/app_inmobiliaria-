<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Domain\Search\Exceptions\SearchException;
use App\Http\Controllers\Concerns\ResolvesClientChannel;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The signed-in user's favorite listings. Only listings that are publicly visible right now
 * are returned or can be added; a favorite that later becomes hidden stays stored (it
 * reappears if the listing is republished) and can always be removed.
 */
class FavoriteController extends Controller
{
    use ResolvesClientChannel;

    public const MAX_FAVORITES = 500;

    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()->favoriteProperties()
            ->publiclyVisible($this->channel($request))
            ->with(['propertyType', 'media', 'mlsListing.provider.displayRule'])
            ->orderByPivot('created_at', 'desc')
            ->paginate(min(max((int) $request->integer('per_page', 24), 1), 48));

        return PublicPropertyResource::collection($favorites->through(fn (Property $p) => (new PublicPropertyResource($p))->summary()));
    }

    /** Slugs of visible favorites, for marking cards in result lists. */
    public function slugs(Request $request): JsonResponse
    {
        $slugs = $request->user()->favoriteProperties()
            ->publiclyVisible($this->channel($request))
            ->pluck('properties.slug');

        return response()->json(['data' => $slugs]);
    }

    public function store(Request $request, string $slug): Response
    {
        $user = $request->user();
        $property = Property::query()->publiclyVisible($this->channel($request))->where('slug', $slug)->firstOrFail();

        if (! $user->favoriteProperties()->whereKey($property->id)->exists()) {
            if ($user->favoriteProperties()->count() >= self::MAX_FAVORITES) {
                throw SearchException::favoritesLimit(self::MAX_FAVORITES);
            }
            $user->favoriteProperties()->attach($property->id, ['created_at' => now()]);
        }

        return response()->noContent();
    }

    public function destroy(Request $request, string $slug): Response
    {
        $propertyId = Property::query()->withTrashed()->where('slug', $slug)->value('id');
        if ($propertyId !== null) {
            $request->user()->favoriteProperties()->detach($propertyId);
        }

        return response()->noContent();
    }
}
