<?php

namespace App\Application\Agency;

use App\Application\Audit\AuditLogger;
use App\Domain\Agency\Enums\AgentStatus;
use App\Domain\Agency\Exceptions\AgencyException;
use App\Domain\Audit\AuditCategory;
use App\Domain\Identity\Enums\RoleName;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Office;
use App\Models\User;
use App\Support\UniqueSlug;

/**
 * Creates and maintains agent profiles. Profiles belong to users that hold
 * the agent or broker role; office must belong to the agent's agency.
 */
class AgentProfileService
{
    /** Fields an agent may edit on their own profile. */
    public const SELF_EDITABLE = ['public_email', 'public_phone', 'bio', 'bio_es', 'languages', 'specialties', 'is_public'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, User $user, Agency $agency, array $attributes): Agent
    {
        if ($user->agentProfile()->withTrashed()->exists()) {
            throw AgencyException::agentProfileExists();
        }
        if (! $user->isActive() || ! $user->hasRole(RoleName::Agent, RoleName::Broker)) {
            throw AgencyException::userNotEligible();
        }
        $this->assertOfficeInAgency($attributes['office_id'] ?? null, $agency);

        $agent = new Agent($attributes);
        $agent->user()->associate($user);
        $agent->agency()->associate($agency);
        $agent->slug = UniqueSlug::for($user->full_name, Agent::query());
        $agent->save();

        $this->audit->log(AuditCategory::Admin, 'agents.created', $actor, $agent, new: [
            'user_id' => $user->id,
            'agency_id' => $agency->id,
            'office_id' => $agent->office_id,
        ]);

        return $agent;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, Agent $agent, array $attributes): Agent
    {
        if (array_key_exists('office_id', $attributes)) {
            $this->assertOfficeInAgency($attributes['office_id'], $agent->agency);
        }

        $agent->fill($attributes);
        $changes = $agent->getDirty();
        $original = array_intersect_key($agent->getOriginal(), $changes);
        $agent->save();

        if ($changes !== []) {
            $event = $actor->is($agent->user) ? 'agents.profile_self_updated' : 'agents.updated';
            $this->audit->log(AuditCategory::Admin, $event, $actor, $agent, $this->plain($original), $this->plain($changes));
        }

        return $agent;
    }

    public function setStatus(User $actor, Agent $agent, AgentStatus $status): Agent
    {
        if ($agent->status !== $status) {
            $before = $agent->status->value;
            $agent->forceFill(['status' => $status])->save();
            $this->audit->log(AuditCategory::Admin, 'agents.status_changed', $actor, $agent, ['status' => $before], ['status' => $status->value]);
        }

        return $agent;
    }

    private function assertOfficeInAgency(mixed $officeId, Agency $agency): void
    {
        if ($officeId !== null && ! Office::query()->whereKey($officeId)->where('agency_id', $agency->id)->exists()) {
            throw AgencyException::officeOutsideAgency();
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn ($v) => is_string($v) && json_validate($v) && in_array($v[0] ?? '', ['[', '{'], true) ? json_decode($v, true) : $v, $values);
    }
}
