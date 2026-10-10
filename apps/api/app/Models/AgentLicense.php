<?php

namespace App\Models;

use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Agency\Enums\LicenseType;
use App\Domain\Agency\Enums\VerificationSource;
use App\Support\AgencyClock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status, verifier and timestamps are only changed through
 * LicenseVerificationService (never mass-assigned).
 */
#[Fillable(['license_number', 'license_state', 'license_type', 'issued_on', 'expires_on'])]
class AgentLicense extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending_verification', 'license_state' => 'NJ'];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'license_type' => LicenseType::class,
            'verification_source' => VerificationSource::class,
            'issued_on' => 'date',
            'expires_on' => 'date',
            'verified_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isCurrentlyValid(): bool
    {
        return $this->status === LicenseStatus::Verified
            && ($this->expires_on === null || $this->expires_on->toDateString() >= AgencyClock::todayString());
    }
}
