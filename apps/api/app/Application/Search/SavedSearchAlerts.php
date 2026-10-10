<?php

namespace App\Application\Search;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Search\Enums\AlertFrequency;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Notifications\Search\SavedSearchMatchesNotification;
use Illuminate\Support\Carbon;

/**
 * Notifies owners of saved searches about listings published since their last alert. Matching
 * runs through PropertySearch on the public web channel, so alerts never include listings the
 * public search would hide (drafts, MLS listings without public display rights, removals).
 */
final class SavedSearchAlerts
{
    public const MAX_LISTED = 10;

    public function __construct(private readonly PropertySearch $search) {}

    /**
     * @return array{searches: int, notified: int}
     */
    public function run(AlertFrequency $frequency, ?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['searches' => 0, 'notified' => 0];

        SavedSearch::query()
            ->where('alert_frequency', $frequency->value)
            ->whereHas('user', fn ($u) => $u->where('status', UserStatus::Active->value))
            ->with('user')
            ->chunkById(200, function ($searches) use ($now, &$stats) {
                foreach ($searches as $saved) {
                    $stats['searches']++;
                    if ($this->alert($saved, $now)) {
                        $stats['notified']++;
                    }
                }
            });

        return $stats;
    }

    private function alert(SavedSearch $saved, Carbon $now): bool
    {
        $user = $saved->user;
        $since = $saved->last_alerted_at ?? $saved->created_at;

        // Permission can be removed after the search was saved: stop alerting silently.
        if (! $user->isSuperAdmin() && ! $user->hasPermission(Permission::SavedSearchesManage)) {
            return false;
        }

        $query = $this->search->apply(
            Property::query()->publiclyVisible('web')->where('properties.published_at', '>', $since)->where('properties.published_at', '<=', $now),
            $saved->filters ?? [],
            'newest',
        );
        $total = (clone $query)->count();

        $saved->forceFill(['last_alerted_at' => $now])->save();
        if ($total === 0) {
            return false;
        }

        $listings = $query->limit(self::MAX_LISTED)->get(['properties.id', 'slug', 'title', 'list_price', 'listing_type', 'city', 'state'])
            ->map(fn (Property $p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'list_price' => $p->list_price !== null ? (float) $p->list_price : null,
                'listing_type' => $p->listing_type->value,
                'city' => $p->city,
                'state' => $p->state,
            ])->all();

        $user->notify(new SavedSearchMatchesNotification($saved, $listings, $total));

        return true;
    }
}
