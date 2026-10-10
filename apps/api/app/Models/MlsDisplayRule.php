<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'attribution_template', 'disclaimer', 'min_refresh_minutes', 'retention_days',
    'allowed_fields', 'photo_rules', 'allow_public_display', 'allow_mobile_display',
    'allow_ai_processing', 'hide_withdrawn_immediately',
])]
class MlsDisplayRule extends Model
{
    protected function casts(): array
    {
        return [
            'allowed_fields' => 'array',
            'photo_rules' => 'array',
            'allow_public_display' => 'boolean',
            'allow_mobile_display' => 'boolean',
            'allow_ai_processing' => 'boolean',
            'hide_withdrawn_immediately' => 'boolean',
            'min_refresh_minutes' => 'integer',
            'retention_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MlsProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(MlsProvider::class, 'mls_provider_id');
    }
}
