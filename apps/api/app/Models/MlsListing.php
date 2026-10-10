<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Raw, provider-scoped copy of an MLS record (source of truth for compliance
 * fields). Written only by the sync pipeline.
 */
class MlsListing extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'internet_display_allowed' => 'boolean',
            'address_display_allowed' => 'boolean',
            'source_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'removed_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MlsProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(MlsProvider::class, 'mls_provider_id');
    }

    /**
     * @return HasOne<Property, $this>
     */
    public function property(): HasOne
    {
        return $this->hasOne(Property::class)->withTrashed();
    }
}
