<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Domain\Search\Exceptions\SearchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Saved searches belong to their owner only; other users' ids resolve to 404. */
class SavedSearchController extends Controller
{
    public const MAX_SAVED_SEARCHES = 25;

    public function index(Request $request): AnonymousResourceCollection
    {
        return SavedSearchResource::collection($request->user()->savedSearches()->latest('id')->get());
    }

    public function store(SavedSearchRequest $request): SavedSearchResource
    {
        $user = $request->user();
        if ($user->savedSearches()->count() >= self::MAX_SAVED_SEARCHES) {
            throw SearchException::savedSearchLimit(self::MAX_SAVED_SEARCHES);
        }

        // Alerts only cover listings published after the search was saved.
        $search = $user->savedSearches()->create([...$request->attributesForModel(), 'last_alerted_at' => now()]);

        return new SavedSearchResource($search);
    }

    public function update(SavedSearchRequest $request, int $savedSearch): SavedSearchResource
    {
        $search = $this->owned($request, $savedSearch);
        $search->update($request->attributesForModel());

        return new SavedSearchResource($search);
    }

    public function destroy(Request $request, int $savedSearch): Response
    {
        $this->owned($request, $savedSearch)->delete();

        return response()->noContent();
    }

    private function owned(Request $request, int $id): SavedSearch
    {
        return $request->user()->savedSearches()->whereKey($id)->firstOrFail();
    }
}
