<?php

namespace App\Models;

use App\Domain\Deals\Enums\Financing;
use App\Domain\Deals\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    protected $fillable = [
        'property_id', 'parent_id', 'lead_id', 'buyer_agent_id', 'buyer_agent_external', 'buyer_name', 'side', 'amount',
        'earnest_money', 'financing', 'down_payment_percent', 'closing_date', 'contingencies', 'terms', 'expires_at', 'created_by',
    ];

    protected $attributes = ['status' => 'draft', 'side' => 'buyer'];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'financing' => Financing::class,
            'amount' => 'decimal:2',
            'earnest_money' => 'decimal:2',
            'down_payment_percent' => 'decimal:2',
            'closing_date' => 'date',
            'contingencies' => 'array',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function buyerAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'buyer_agent_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /** First offer of the counter chain. */
    public function root(): self
    {
        $offer = $this;
        while ($offer->parent_id !== null && ($parent = $offer->parent) !== null) {
            $offer = $parent;
        }

        return $offer;
    }
}
