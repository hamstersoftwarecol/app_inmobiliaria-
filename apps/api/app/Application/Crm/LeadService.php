<?php

namespace App\Application\Crm;

use App\Application\Audit\AuditLogger;
use App\Domain\Agency\Enums\AgentStatus;
use App\Domain\Audit\AuditCategory;
use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Enums\LeadStatus;
use App\Domain\Crm\Exceptions\CrmException;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Property;
use App\Models\User;
use App\Notifications\Crm\LeadAssignedNotification;
use App\Notifications\Crm\UnassignedLeadNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class LeadService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Public inquiry (listing, agent profile or general contact form). An open lead with the same
     * e-mail or phone receives the inquiry as a new timeline entry instead of a duplicate lead.
     *
     * @param  array{first_name: string, last_name?: ?string, email?: ?string, phone?: ?string, message?: ?string, intent?: ?string, preferred_language?: ?string, marketing_consent?: bool}  $data
     */
    public function recordInquiry(array $data, LeadSource $source, ?Property $property, ?Agent $agent, ?string $ip): Lead
    {
        return DB::transaction(function () use ($data, $source, $property, $agent, $ip) {
            $emailKey = LeadKeys::email($data['email'] ?? null);
            $phoneKey = LeadKeys::phone($data['phone'] ?? null);
            $context = array_filter([
                'source' => $source->value,
                'property_slug' => $property?->slug,
                'property_title' => $property?->title,
                'agent_slug' => $agent?->slug,
            ]);

            $existing = $this->openMatch($emailKey, $phoneKey);
            if ($existing !== null) {
                $existing->forceFill([
                    'marketing_consent' => $existing->marketing_consent || (bool) ($data['marketing_consent'] ?? false),
                    'last_activity_at' => now(),
                ])->save();
                $this->addActivity($existing, null, ActivityType::Inquiry, $data['message'] ?? null, $context);

                return $existing;
            }

            $lead = new Lead([
                'agency_id' => $property?->agency_id ?? $agent?->agency_id,
                'property_id' => $property?->id,
                'source' => $source,
                'intent' => $data['intent'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'message' => $data['message'] ?? null,
                'preferred_language' => $data['preferred_language'] ?? null,
            ]);
            $lead->forceFill([
                'email_key' => $emailKey,
                'phone_key' => $phoneKey,
                'contact_consent' => true,
                'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
                'consent_at' => now(),
                'consent_ip' => $ip,
                'last_activity_at' => now(),
            ])->save();
            $this->addActivity($lead, null, ActivityType::Inquiry, $data['message'] ?? null, $context);

            $assignee = $this->activeAgent($property?->listing_agent_id) ?? ($agent && $agent->status === AgentStatus::Active ? $agent : null);
            if ($assignee !== null) {
                $this->applyAssignment($lead, $assignee, null);
            } else {
                Notification::send($this->assigners(), new UnassignedLeadNotification($lead));
            }

            return $lead;
        });
    }

    /**
     * Lead entered by staff or an agent. Agents without `leads.assign` always own what they create.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Lead
    {
        $agentId = $data['assigned_agent_id'] ?? null;
        unset($data['assigned_agent_id']);

        return DB::transaction(function () use ($actor, $data, $agentId) {
            $lead = new Lead([...$data, 'created_by' => $actor->id]);
            $lead->forceFill([
                'email_key' => LeadKeys::email($data['email'] ?? null),
                'phone_key' => LeadKeys::phone($data['phone'] ?? null),
                'contact_consent' => (bool) ($data['contact_consent'] ?? false),
                'consent_at' => ($data['contact_consent'] ?? false) ? now() : null,
                'last_activity_at' => now(),
            ])->save();

            if ($this->canAssign($actor)) {
                if ($agentId !== null) {
                    $this->applyAssignment($lead, $this->assignableAgent($agentId), $actor);
                }
            } else {
                $own = $actor->agentProfile;
                if ($own === null) {
                    throw CrmException::noAgentProfile();
                }
                $this->applyAssignment($lead, $own, $actor, notify: false);
            }

            return $lead;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data): Lead
    {
        $lead->fill($data);
        if (array_key_exists('email', $data)) {
            $lead->email_key = LeadKeys::email($data['email']);
        }
        if (array_key_exists('phone', $data)) {
            $lead->phone_key = LeadKeys::phone($data['phone']);
        }
        $lead->save();

        return $lead;
    }

    public function changeStatus(User $actor, Lead $lead, LeadStatus $status, ?string $lostReason): Lead
    {
        if ($lead->status === $status) {
            return $lead;
        }
        $from = $lead->status;

        return DB::transaction(function () use ($actor, $lead, $status, $lostReason, $from) {
            $lead->forceFill([
                'status' => $status,
                'lost_reason' => $status === LeadStatus::Lost ? $lostReason : null,
                'closed_at' => $status->isOpen() ? null : now(),
                'last_activity_at' => now(),
            ])->save();
            $this->addActivity($lead, $actor, ActivityType::StatusChange, $status === LeadStatus::Lost ? $lostReason : null, [
                'from' => $from->value, 'to' => $status->value,
            ]);

            return $lead;
        });
    }

    public function assign(User $actor, Lead $lead, ?int $agentId): Lead
    {
        $agent = $agentId !== null ? $this->assignableAgent($agentId) : null;
        if ($agent?->id === $lead->assigned_agent_id) {
            return $lead;
        }
        $before = $lead->assigned_agent_id;

        return DB::transaction(function () use ($actor, $lead, $agent, $before) {
            if ($agent === null) {
                $lead->forceFill(['assigned_agent_id' => null, 'assigned_at' => null])->save();
                $this->addActivity($lead, $actor, ActivityType::Assignment, null, ['agent_id' => null]);
            } else {
                $this->applyAssignment($lead, $agent, $actor);
            }
            $this->audit->log(AuditCategory::Data, 'leads.assigned', $actor, $lead, ['assigned_agent_id' => $before], ['assigned_agent_id' => $agent?->id]);

            return $lead;
        });
    }

    public function logActivity(User $actor, Lead $lead, ActivityType $type, ?string $body, ?Carbon $occurredAt): LeadActivity
    {
        return DB::transaction(function () use ($actor, $lead, $type, $body, $occurredAt) {
            $activity = $this->addActivity($lead, $actor, $type, $body, [], $occurredAt);
            $updates = ['last_activity_at' => now()];
            if ($type->isContact() && $lead->first_contacted_at === null) {
                $updates['first_contacted_at'] = $activity->occurred_at;
            }
            $lead->forceFill($updates)->save();
            // Logging the first real contact moves a new lead forward.
            if ($type->isContact() && $lead->status === LeadStatus::New) {
                $this->changeStatus($actor, $lead, LeadStatus::Contacted, null);
            }

            return $activity;
        });
    }

    /**
     * Folds `$source` into `$target`: timeline and tasks move, missing contact details are copied,
     * and the source lead is soft-deleted.
     */
    public function merge(User $actor, Lead $source, Lead $target): Lead
    {
        if ($source->is($target)) {
            throw CrmException::mergeInvalid('A lead cannot be merged into itself.');
        }

        return DB::transaction(function () use ($actor, $source, $target) {
            LeadActivity::query()->where('lead_id', $source->id)->toBase()->update(['lead_id' => $target->id]);
            $source->tasks()->update(['lead_id' => $target->id]);

            foreach (['last_name', 'email', 'phone', 'intent', 'property_id', 'budget_min', 'budget_max', 'preferred_language'] as $field) {
                if ($target->{$field} === null && $source->{$field} !== null) {
                    $target->{$field} = $source->{$field};
                }
            }
            $target->email_key ??= $source->email_key;
            $target->phone_key ??= $source->phone_key;
            $target->marketing_consent = $target->marketing_consent || $source->marketing_consent;
            $target->last_activity_at = now();
            $target->save();

            $this->addActivity($target, $actor, ActivityType::Merge, null, ['merged_lead_id' => $source->id, 'merged_name' => $source->full_name]);
            $source->delete();
            $this->audit->log(AuditCategory::Data, 'leads.merged', $actor, $target, null, null, ['merged_lead_id' => $source->id]);

            return $target->refresh();
        });
    }

    public function delete(User $actor, Lead $lead): void
    {
        $lead->delete();
        $this->audit->log(AuditCategory::Data, 'leads.deleted', $actor, $lead, ['status' => $lead->status->value, 'source' => $lead->source->value]);
    }

    /**
     * Other leads with the same normalized e-mail or phone.
     *
     * @return Collection<int, Lead>
     */
    public function duplicatesOf(Lead $lead): Collection
    {
        if ($lead->email_key === null && $lead->phone_key === null) {
            return new Collection;
        }

        return Lead::query()
            ->whereKeyNot($lead->id)
            ->where(fn ($q) => $q
                ->when($lead->email_key, fn ($w) => $w->orWhere('email_key', $lead->email_key))
                ->when($lead->phone_key, fn ($w) => $w->orWhere('phone_key', $lead->phone_key)))
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function canAssign(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->hasPermission(Permission::LeadsAssign);
    }

    private function openMatch(?string $emailKey, ?string $phoneKey): ?Lead
    {
        if ($emailKey === null && $phoneKey === null) {
            return null;
        }

        return Lead::query()
            ->whereIn('status', LeadStatus::openValues())
            ->where(fn ($q) => $q
                ->when($emailKey, fn ($w) => $w->orWhere('email_key', $emailKey))
                ->when($phoneKey, fn ($w) => $w->orWhere('phone_key', $phoneKey)))
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    private function activeAgent(?int $id): ?Agent
    {
        return $id === null ? null : Agent::query()->whereKey($id)->where('status', AgentStatus::Active->value)->first();
    }

    private function assignableAgent(int $id): Agent
    {
        return $this->activeAgent($id) ?? throw CrmException::agentNotAssignable();
    }

    private function applyAssignment(Lead $lead, Agent $agent, ?User $actor, bool $notify = true): void
    {
        $lead->forceFill(['assigned_agent_id' => $agent->id, 'assigned_at' => now()])->save();
        $this->addActivity($lead, $actor, ActivityType::Assignment, null, ['agent_id' => $agent->id, 'agent_name' => $agent->user?->full_name]);
        $user = $agent->user;
        if ($notify && $user !== null && $user->id !== $actor?->id && $user->status === UserStatus::Active) {
            $user->notify(new LeadAssignedNotification($lead));
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function addActivity(Lead $lead, ?User $actor, ActivityType $type, ?string $body, array $metadata = [], ?Carbon $occurredAt = null): LeadActivity
    {
        return LeadActivity::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'body' => $body,
            'metadata' => $metadata ?: null,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assigners(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles.permissions', fn ($q) => $q->where('name', Permission::LeadsAssign->value))
            ->get();
    }
}
