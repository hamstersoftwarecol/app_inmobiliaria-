<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PropertyMedia extends Model
{
    protected $table = 'property_media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['display_allowed' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function publicUrl(): ?string
    {
        if ($this->source === 'mls') {
            return $this->url;
        }

        return $this->disk && $this->path ? Storage::disk($this->disk)->url($this->path) : null;
    }
}
