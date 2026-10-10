<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Credentials are encrypted at rest (APP_KEY) and hidden from serialization.
 * They are only ever read server-side by MLS adapters.
 */
#[Fillable(['name', 'environment', 'base_url', 'token_url', 'credentials'])]
#[Hidden(['credentials'])]
class MlsConnection extends Model
{
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'environment' => 'sandbox',
        'status' => 'inactive',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_authenticated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MlsProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(MlsProvider::class, 'mls_provider_id');
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * Names of the credential keys present (never the values), for admin UIs.
     *
     * @return list<string>
     */
    public function credentialKeys(): array
    {
        return array_keys($this->credentials ?? []);
    }
}
