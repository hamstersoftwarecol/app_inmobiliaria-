<?php

namespace App\Models;

use App\Domain\Mls\Enums\ProviderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'driver', 'protocol', 'website', 'notes'])]
class MlsProvider extends Model
{
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'protocol' => 'reso_web_api',
        'status' => 'pending_authorization',
        'is_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => ProviderStatus::class,
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MlsConnection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(MlsConnection::class);
    }

    /**
     * @return HasOne<MlsDisplayRule, $this>
     */
    public function displayRule(): HasOne
    {
        return $this->hasOne(MlsDisplayRule::class);
    }

    /**
     * @return HasMany<MlsFieldMapping, $this>
     */
    public function fieldMappings(): HasMany
    {
        return $this->hasMany(MlsFieldMapping::class);
    }

    public function isAuthorizedForProduction(): bool
    {
        return $this->is_enabled && $this->status === ProviderStatus::Authorized;
    }
}
