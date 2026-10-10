<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['subject', 'property_id', 'transaction_id', 'created_by', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withPivot(['last_read_at', 'joined_at']);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** Only participants ever see a conversation (no admin override: messages are private). */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->whereHas('participants', fn (Builder $p) => $p->where('users.id', $user->id));
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('users.id', $user->id)->exists();
    }
}
