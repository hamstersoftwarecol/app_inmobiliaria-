<?php

namespace App\Models;

use App\Domain\Audit\AuditCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only: updates and deletes are rejected at model level.
 */
#[Fillable([
    'user_id', 'category', 'event', 'auditable_type', 'auditable_id',
    'old_values', 'new_values', 'metadata', 'ip_address', 'user_agent', 'request_id',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'category' => AuditCategory::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit logs are immutable.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
