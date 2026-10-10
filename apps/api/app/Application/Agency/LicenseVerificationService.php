<?php

namespace App\Application\Agency;

use App\Application\Audit\AuditLogger;
use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Agency\Enums\VerificationSource;
use App\Domain\Agency\Exceptions\AgencyException;
use App\Domain\Audit\AuditCategory;
use App\Models\Agent;
use App\Models\AgentLicense;
use App\Models\User;
use App\Notifications\Agency\LicenseStatusChangedNotification;
use App\Support\AgencyClock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Licence verification workflow.
 *
 * - Submissions always start as pending_verification (whoever submits).
 * - Only users with agents.verify_licenses may verify/reject/revoke, never
 *   on their own licence (separation of duties).
 * - Verification records how it was checked (source + reference) and the
 *   expiration date; a daily job expires licences past that date.
 */
class LicenseVerificationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{license_number: string, license_state?: string, license_type: string, issued_on?: string|null, expires_on?: string|null}  $data
     */
    public function submit(User $actor, Agent $agent, array $data): AgentLicense
    {
        $data['license_number'] = strtoupper(preg_replace('/\s+/', '', $data['license_number']) ?? '');
        $data['license_state'] = strtoupper($data['license_state'] ?? 'NJ');

        $duplicate = AgentLicense::query()
            ->where('license_state', $data['license_state'])
            ->whereRaw('upper(license_number) = ?', [$data['license_number']])
            ->whereIn('status', [LicenseStatus::PendingVerification->value, LicenseStatus::Verified->value])
            ->exists();
        if ($duplicate) {
            throw AgencyException::duplicateLicense();
        }

        $license = new AgentLicense($data);
        $license->agent()->associate($agent);
        $license->submitted_by = $actor->id;

        try {
            $license->save();
        } catch (QueryException $e) {
            // Race with a concurrent submission: the partial unique index wins.
            if ($e->getCode() === '23505') {
                throw AgencyException::duplicateLicense();
            }
            throw $e;
        }

        $this->audit->log(AuditCategory::Admin, 'licenses.submitted', $actor, $license, new: [
            'agent_id' => $agent->id,
            'license_state' => $license->license_state,
            'license_type' => $license->license_type->value,
            'license_number' => $license->license_number,
        ]);

        return $license;
    }

    public function verify(User $actor, AgentLicense $license, VerificationSource $source, string $reference, ?string $expiresOn, ?string $notes): AgentLicense
    {
        $expires = $expiresOn !== null ? Carbon::parse($expiresOn) : $license->expires_on;
        if ($expires !== null && $expires->toDateString() < AgencyClock::todayString()) {
            throw AgencyException::licenseAlreadyExpired();
        }

        return $this->transition($actor, $license, LicenseStatus::Verified, null, function (AgentLicense $l) use ($actor, $source, $reference, $expires, $notes) {
            $l->forceFill([
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_source' => $source,
                'verification_reference' => $reference,
                'verification_notes' => $notes,
                'expires_on' => $expires?->toDateString(),
            ]);
        });
    }

    public function reject(User $actor, AgentLicense $license, string $reason): AgentLicense
    {
        return $this->transition($actor, $license, LicenseStatus::Rejected, $reason);
    }

    public function revoke(User $actor, AgentLicense $license, string $reason): AgentLicense
    {
        return $this->transition($actor, $license, LicenseStatus::Revoked, $reason);
    }

    /**
     * Marks verified licences whose expiration date has passed (agency time zone) as expired.
     *
     * @return int number of licences expired
     */
    public function expireDue(?Carbon $today = null): int
    {
        $today ??= AgencyClock::today();
        $count = 0;

        AgentLicense::query()
            ->where('status', LicenseStatus::Verified->value)
            ->whereNotNull('expires_on')
            ->where('expires_on', '<', $today->toDateString())
            ->with('agent.user')
            ->chunkById(100, function ($licenses) use (&$count) {
                foreach ($licenses as $license) {
                    $this->transition(null, $license, LicenseStatus::Expired, 'Expiration date reached.');
                    $count++;
                }
            });

        return $count;
    }

    private function transition(?User $actor, AgentLicense $license, LicenseStatus $to, ?string $reason, ?callable $mutate = null): AgentLicense
    {
        $license->loadMissing('agent.user');

        if ($actor !== null && $license->agent->user_id === $actor->id) {
            throw AgencyException::selfVerification();
        }

        DB::transaction(function () use ($actor, $license, $to, $reason, $mutate) {
            /** @var AgentLicense $locked */
            $locked = AgentLicense::query()->lockForUpdate()->findOrFail($license->id);
            if (! $locked->status->canTransitionTo($to)) {
                throw AgencyException::invalidLicenseTransition($locked->status, $to);
            }

            $before = $locked->status->value;
            $license->forceFill([
                'status' => $to,
                'status_reason' => $reason,
                'status_changed_by' => $actor?->id,
                'status_changed_at' => now(),
            ]);
            if ($mutate) {
                $mutate($license);
            }
            $license->save();

            $this->audit->log(AuditCategory::Admin, 'licenses.'.$to->value, $actor, $license, ['status' => $before], array_filter([
                'status' => $to->value,
                'reason' => $reason,
                'verification_source' => $license->verification_source?->value,
                'verification_reference' => $to === LicenseStatus::Verified ? $license->verification_reference : null,
                'expires_on' => $to === LicenseStatus::Verified ? $license->expires_on?->toDateString() : null,
            ]), $actor === null ? ['system' => 'license-expiry'] : []);
        });

        $license->agent->user?->notify(new LicenseStatusChangedNotification($license));

        return $license;
    }
}
