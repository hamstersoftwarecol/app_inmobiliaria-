<?php

namespace App\Models;

use App\Domain\Search\Enums\AlertFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $fillable = ['user_id', 'name', 'filters', 'sort', 'alert_frequency', 'last_alerted_at'];

    protected $attributes = ['alert_frequency' => 'daily'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'alert_frequency' => AlertFrequency::class,
            'last_alerted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
