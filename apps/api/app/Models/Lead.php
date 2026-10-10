<?php

namespace App\Models;

use App\Domain\Crm\Enums\LeadIntent;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Enums\LeadStatus;
use App\Domain\Identity\Enums\Permission;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id', 'property_id', 'created_by', 'source', 'intent', 'first_name', 'last_name', 'email', 'phone',
        'message', 'budget_min', 'budget_max', 'preferences', 'preferred_language',
    ];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'intent' => LeadIntent::class,
            'preferences' => 'array',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'contact_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'consent_at' => 'datetime',
            'assigned_at' => 'datetime',
            'first_contacted_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }

    /** Leads the user may see: everything with `leads.view_any`, otherwise those assigned to their agent profile. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin() || $user->hasPermission(Permission::LeadsViewAny)) {
            return;
        }
        $query->whereHas('assignedAgent', fn (Builder $a) => $a->where('user_id', $user->id));
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_agent_id !== null && $this->assignedAgent()->where('user_id', $user->id)->exists();
    }
}
