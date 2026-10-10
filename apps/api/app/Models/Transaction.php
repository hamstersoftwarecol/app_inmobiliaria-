<?php

namespace App\Models;

use App\Domain\Deals\Enums\TransactionStatus;
use App\Domain\Identity\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A deal under contract, from the accepted offer to closing (not a database transaction). */
class Transaction extends Model
{
    protected $fillable = ['property_id', 'offer_id', 'listing_agent_id', 'buyer_agent_id', 'sale_price', 'contract_date', 'closing_date', 'created_by'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'sale_price' => 'decimal:2',
            'contract_date' => 'date',
            'closing_date' => 'date',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function listingAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'listing_agent_id');
    }

    public function buyerAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'buyer_agent_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TransactionParticipant::class)->orderBy('id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(TransactionMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function isAgentOn(User $user): bool
    {
        return Agent::query()->where('user_id', $user->id)->whereIn('id', array_filter([$this->listing_agent_id, $this->buyer_agent_id]))->exists();
    }

    public function isClient(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    /** `transactions.view_any` sees all; agents those they work on; clients those they take part in. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin() || $user->hasPermission(Permission::TransactionsViewAny)) {
            return;
        }
        $query->where(fn (Builder $q) => $q
            ->whereHas('listingAgent', fn (Builder $a) => $a->where('user_id', $user->id))
            ->orWhereHas('buyerAgent', fn (Builder $a) => $a->where('user_id', $user->id))
            ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id)));
    }
}
