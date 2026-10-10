<?php

namespace App\Models;

use App\Domain\Agency\Enums\AgentStatus;
use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'office_id', 'public_email', 'public_phone', 'bio', 'bio_es', 'languages',
    'specialties', 'photo_url', 'is_public', 'joined_on',
])]
class Agent extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'is_public' => false];

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'languages' => 'array',
            'specialties' => 'array',
            'is_public' => 'boolean',
            'joined_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * @return HasMany<AgentLicense, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(AgentLicense::class)->latest('id');
    }

    /**
     * Most recent currently-valid verified licence.
     *
     * @return HasOne<AgentLicense, $this>
     */
    public function verifiedLicense(): HasOne
    {
        return $this->hasOne(AgentLicense::class)->ofMany(
            ['verified_at' => 'max', 'id' => 'max'],
            fn (Builder $q) => $q->where('status', LicenseStatus::Verified->value)
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today()->toDateString())),
        );
    }

    /**
     * Public directory: active and opted-in agent, active user account, and a
     * verified, unexpired licence.
     *
     * @param  Builder<Agent>  $query
     */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), AgentStatus::Active->value)
            ->where($this->qualifyColumn('is_public'), true)
            ->whereHas('user', fn ($q) => $q->where('status', UserStatus::Active->value))
            ->whereHas('licenses', fn ($q) => $q->where('status', LicenseStatus::Verified->value)
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today()->toDateString())));
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
