<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MlsSyncJob extends Model
{
    public const TYPES = ['import', 'incremental', 'reconcile', 'media'];

    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MlsConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(MlsConnection::class, 'mls_connection_id');
    }

    /**
     * @return HasMany<MlsSyncLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MlsSyncLog::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
