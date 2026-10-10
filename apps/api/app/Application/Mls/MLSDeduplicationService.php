<?php

namespace App\Application\Mls;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Within one provider, records are deduplicated by (provider, ListingKey)
 * in MLSListingSyncService. Across providers the same home may be listed in
 * two MLSs; those candidates are reported for human review (never merged
 * automatically, because each MLS has its own attribution rules).
 */
class MLSDeduplicationService
{
    /**
     * @return Collection<int, object{address_key: string, property_ids: string, providers: int}>
     */
    public function crossProviderCandidates(int $limit = 50): Collection
    {
        return collect(DB::select(<<<'SQL'
            SELECT lower(regexp_replace(p.street_address, '\s+', ' ', 'g')) || '|' || coalesce(lower(p.unit), '') || '|' || p.postal_code AS address_key,
                   string_agg(p.id::text, ',' ORDER BY p.id) AS property_ids,
                   count(DISTINCT ml.mls_provider_id) AS providers
            FROM properties p
            JOIN mls_listings ml ON ml.id = p.mls_listing_id
            WHERE p.is_published AND p.deleted_at IS NULL AND p.street_address IS NOT NULL AND p.postal_code IS NOT NULL
            GROUP BY 1
            HAVING count(DISTINCT ml.mls_provider_id) > 1
            ORDER BY 1
            LIMIT ?
        SQL, [$limit]));
    }
}
