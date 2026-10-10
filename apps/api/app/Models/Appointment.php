<?php

namespace App\Models;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Showings\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'property_id', 'agent_id', 'lead_id', 'requested_by', 'created_by', 'contact_name', 'contact_email', 'contact_phone',
        'starts_at', 'ends_at', 'timezone', 'notes',
    ];

    protected $attributes = ['status' => 'requested'];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime',
            'reminder_1h_sent_at' => 'datetime',
            'feedback_rating' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Requesters see their own; agents those they host; `appointments.view_any` sees all. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin() || $user->hasPermission(Permission::AppointmentsViewAny)) {
            return;
        }
        $query->where(fn (Builder $q) => $q
            ->where('requested_by', $user->id)
            ->orWhereHas('agent', fn (Builder $a) => $a->where('user_id', $user->id)));
    }

    public function isHostedBy(User $user): bool
    {
        return $this->agent()->where('user_id', $user->id)->exists();
    }
}
