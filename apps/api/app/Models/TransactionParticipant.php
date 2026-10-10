<?php

namespace App\Models;

use App\Domain\Deals\Enums\ParticipantRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionParticipant extends Model
{
    protected $fillable = ['transaction_id', 'user_id', 'role', 'name', 'email', 'phone'];

    protected function casts(): array
    {
        return ['role' => ParticipantRole::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
