<?php

namespace App\Application\Mls;

use App\Domain\Mls\Contracts\MLSProviderInterface;
use App\Models\MlsConnection;
use App\Models\MlsListing;
use App\Models\PropertyMedia;
use Illuminate\Support\Facades\DB;

/**
 * Keeps MLS photo references in sync. Photos are referenced by the provider's
 * HTTPS URL (not copied) unless the licence explicitly allows storage, and
 * each photo carries the display permission computed from the provider rules.
 */
class MLSMediaService
{
    public function __construct(private readonly MLSComplianceService $compliance) {}

    public function sync(MlsConnection $connection, MLSProviderInterface $provider, MlsListing $listing): int
    {
        $property = $listing->property()->first();
        if ($property === null) {
            return 0;
        }

        $rules = $connection->provider->displayRule;
        $max = (int) config('mls.sync.max_photos', 50);
        $items = [];
        foreach ($provider->fetchMedia($listing->listing_key) as $media) {
            if (count($items) >= $max) {
                break;
            }
            $items[] = $media;
        }

        DB::transaction(function () use ($property, $items, $rules) {
            $keys = [];
            foreach ($items as $i => $media) {
                $keys[] = $media->mediaKey;
                PropertyMedia::query()->updateOrCreate(
                    ['property_id' => $property->id, 'mls_media_key' => $media->mediaKey],
                    [
                        'source' => 'mls',
                        'url' => $media->url,
                        'mime_type' => $media->mimeType,
                        'category' => $media->category,
                        'caption' => $media->caption ? mb_substr($media->caption, 0, 300) : null,
                        'sort_order' => $i,
                        'display_allowed' => $this->compliance->canDisplayMedia($media, $rules),
                    ],
                );
            }

            PropertyMedia::query()->where('property_id', $property->id)->where('source', 'mls')
                ->when($keys !== [], fn ($q) => $q->whereNotIn('mls_media_key', $keys))
                ->delete();
        });

        return count($items);
    }
}
