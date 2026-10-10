<?php

namespace App\Models;

use App\Domain\Deals\Enums\MilestoneType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionMilestone extends Model
{
    protected $fillable = ['transaction_id', 'type', 'title', 'due_date', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => MilestoneType::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
