<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAvailability extends Model
{
    protected $fillable = ['agent_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
