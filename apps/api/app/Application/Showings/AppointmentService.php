<?php

namespace App\Application\Showings;

use App\Application\Crm\LeadService;
use App\Domain\Agency\Enums\AgentStatus;
use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Listings\Enums\PropertySource;
use App\Domain\Mls\Enums\ListingStatus;
use App\Domain\Showings\Enums\AppointmentStatus;
use App\Domain\Showings\Exceptions\ShowingException;
use App\Models\Agent;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\Showings\ShowingUpdateNotification;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AppointmentService
{
    public function __construct(
        private readonly ShowingSlots $slots,
        private readonly LeadService $leads,
    ) {}

    /** The agent who hosts online showings for a listing, or null when it cannot be booked online. */
    public function hostFor(Property $property): ?Agent
    {
        $bookable = $property->source === PropertySource::Internal
            && in_array($property->status, [ListingStatus::Active, ListingStatus::ComingSoon], true);
        if (! $bookable || $property->listing_agent_id === null) {
            return null;
        }

        return Agent::query()->whereKey($property->listing_agent_id)->where('status', AgentStatus::Active->value)->whereHas('user')->first();
    }

    /** A signed-in visitor asks for one of the published slots; the request joins (or opens) their CRM lead. */
    public function request(User $requester, Property $property, Carbon $startsAt, ?string $notes, ?string $phone, ?string $ip): Appointment
    {
        $agent = $this->hostFor($property) ?? throw ShowingException::notSchedulable();
        if (! $this->slots->isAvailable($agent, $startsAt)) {
            throw ShowingException::slotUnavailable();
        }

        $appointment = $this->guarded(fn () => DB::transaction(function () use ($requester, $property, $agent, $startsAt, $notes, $phone, $ip) {
            $lead = $this->leads->recordInquiry([
                'first_name' => $requester->first_name,
                'last_name' => $requester->last_name,
                'email' => $requester->email,
                'phone' => $phone ?: $requester->phone,
                'message' => 'Showing requested for '.$startsAt->copy()->setTimezone($agent->timezone())->format('M j, Y g:i A T').($notes ? ". {$notes}" : ''),
                'intent' => $property->listing_type->value === 'rent' ? 'rent' : 'buy',
                'preferred_language' => $requester->locale,
            ], LeadSource::WebsiteListing, $property, null, $ip, notifyAssignee: false);

            return Appointment::query()->create([
                'property_id' => $property->id,
                'agent_id' => $agent->id,
                'lead_id' => $lead->id,
                'requested_by' => $requester->id,
                'created_by' => $requester->id,
                'contact_name' => $requester->full_name,
                'contact_email' => $requester->email,
                'contact_phone' => $phone ?: $requester->phone,
                'starts_at' => $startsAt->copy()->utc(),
                'ends_at' => $startsAt->copy()->utc()->addMinutes((int) config('app_inmobiliaria.showings.duration_minutes', 30)),
                'timezone' => $agent->timezone(),
                'notes' => $notes,
            ]);
        }));

        $agent->user?->notify(new ShowingUpdateNotification($appointment, 'requested'));

        return $appointment;
    }

    /**
     * Agents (own calendar) and managers (`appointments.manage_any`) book directly, confirmed.
     *
     * @param  array<string, mixed>  $data
     */
    public function book(User $actor, Agent $agent, array $data): Appointment
    {
        $startsAt = Carbon::parse($data['starts_at'])->utc();
        if ($startsAt->isPast()) {
            throw ShowingException::inThePast();
        }
        $lead = isset($data['lead_id']) ? Lead::query()->findOrFail($data['lead_id']) : null;

        return $this->guarded(fn () => DB::transaction(function () use ($actor, $agent, $data, $startsAt, $lead) {
            $appointment = new Appointment([
                'property_id' => $data['property_id'] ?? null,
                'agent_id' => $agent->id,
                'lead_id' => $lead?->id,
                'created_by' => $actor->id,
                'contact_name' => $data['contact_name'] ?? $lead?->full_name ?? '',
                'contact_email' => $data['contact_email'] ?? $lead?->email,
                'contact_phone' => $data['contact_phone'] ?? $lead?->phone,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes((int) ($data['duration_minutes'] ?? config('app_inmobiliaria.showings.duration_minutes', 30))),
                'timezone' => $agent->timezone(),
                'notes' => $data['notes'] ?? null,
            ]);
            $appointment->forceFill(['status' => AppointmentStatus::Confirmed, 'confirmed_at' => now(), 'status_changed_by' => $actor->id])->save();
            if ($lead) {
                $this->leads->recordEvent($lead, $actor, ActivityType::Showing, null, ['appointment_id' => $appointment->id, 'event' => 'booked', 'starts_at' => $startsAt->toIso8601String()]);
            }

            return $appointment;
        }));
    }

    public function confirm(User $actor, Appointment $a): Appointment
    {
        $this->requireStatus($a, [AppointmentStatus::Requested], 'confirmed');
        $this->transition($a, $actor, AppointmentStatus::Confirmed, null, ['confirmed_at' => now()]);
        $this->notifyRequester($a, 'confirmed');

        return $a;
    }

    public function decline(User $actor, Appointment $a, string $reason): Appointment
    {
        $this->requireStatus($a, [AppointmentStatus::Requested], 'declined');
        $this->transition($a, $actor, AppointmentStatus::Declined, $reason);
        $this->notifyRequester($a, 'declined');

        return $a;
    }

    public function cancel(User $actor, Appointment $a, string $reason): Appointment
    {
        $this->requireStatus($a, [AppointmentStatus::Requested, AppointmentStatus::Confirmed], 'cancelled');
        $this->transition($a, $actor, AppointmentStatus::Cancelled, $reason, ['cancelled_at' => now()]);
        $this->notifyOtherSide($a, $actor, 'cancelled');

        return $a;
    }

    /**
     * Requesters pick another published slot (back to "requested"); hosts and managers may choose
     * any future time that does not overlap (stays "confirmed").
     */
    public function reschedule(User $actor, Appointment $a, Carbon $startsAt): Appointment
    {
        $this->requireStatus($a, [AppointmentStatus::Requested, AppointmentStatus::Confirmed], 'rescheduled');
        if ($startsAt->isPast()) {
            throw ShowingException::inThePast();
        }
        $byRequester = $a->requested_by === $actor->id && ! $this->hosts($actor, $a);
        if ($byRequester && ! $this->slots->isAvailable($a->agent, $startsAt, $a->id)) {
            throw ShowingException::slotUnavailable();
        }
        $duration = $a->starts_at->diffInMinutes($a->ends_at);

        $this->guarded(fn () => $a->forceFill([
            'starts_at' => $startsAt->copy()->utc(),
            'ends_at' => $startsAt->copy()->utc()->addMinutes($duration),
            'status' => $byRequester ? AppointmentStatus::Requested : AppointmentStatus::Confirmed,
            'confirmed_at' => $byRequester ? null : ($a->confirmed_at ?? now()),
            'status_changed_by' => $actor->id,
            'reminder_24h_sent_at' => null,
            'reminder_1h_sent_at' => null,
        ])->save());
        $this->logOnLead($a, $actor, 'rescheduled');
        $this->notifyOtherSide($a, $actor, 'rescheduled');

        return $a;
    }

    public function complete(User $actor, Appointment $a, AppointmentStatus $outcome, ?int $rating, ?string $comment): Appointment
    {
        $this->requireStatus($a, [AppointmentStatus::Confirmed], $outcome->value);
        if ($a->starts_at->isFuture()) {
            throw ShowingException::notStarted();
        }
        $this->transition($a, $actor, $outcome, null, [
            'completed_at' => now(),
            'feedback_rating' => $rating,
            'feedback_comment' => $comment,
        ]);

        return $a;
    }

    public function hosts(User $user, Appointment $a): bool
    {
        return $a->isHostedBy($user);
    }

    public function canManage(User $user, Appointment $a): bool
    {
        return $user->hasPermission(Permission::AppointmentsManageAny)
            || ($user->hasPermission(Permission::AppointmentsManageOwn) && $this->hosts($user, $a));
    }

    /**
     * @param  list<AppointmentStatus>  $allowed
     */
    private function requireStatus(Appointment $a, array $allowed, string $action): void
    {
        if (! in_array($a->status, $allowed, true)) {
            throw ShowingException::invalidTransition($a->status->value, $action);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(Appointment $a, User $actor, AppointmentStatus $to, ?string $reason, array $extra = []): void
    {
        DB::transaction(function () use ($a, $actor, $to, $reason, $extra) {
            $a->forceFill([...$extra, 'status' => $to, 'status_reason' => $reason, 'status_changed_by' => $actor->id])->save();
            $this->logOnLead($a, $actor, $to->value, $reason);
        });
    }

    private function logOnLead(Appointment $a, User $actor, string $event, ?string $body = null): void
    {
        if ($a->lead_id !== null && ($lead = Lead::query()->find($a->lead_id)) !== null) {
            $this->leads->recordEvent($lead, $actor, ActivityType::Showing, $body ?? $a->feedback_comment, [
                'appointment_id' => $a->id, 'event' => $event, 'starts_at' => $a->starts_at->toIso8601String(), 'rating' => $a->feedback_rating,
            ]);
        }
    }

    private function notifyRequester(Appointment $a, string $kind): void
    {
        $a->requester?->notify(new ShowingUpdateNotification($a, $kind));
    }

    private function notifyOtherSide(Appointment $a, User $actor, string $kind): void
    {
        $recipient = $a->requested_by === $actor->id ? $a->agent?->user : $a->requester;
        if ($recipient !== null && $recipient->id !== $actor->id) {
            $recipient->notify(new ShowingUpdateNotification($a, $kind));
        }
    }

    /**
     * Maps the database no-overlap constraint (SQLSTATE 23P01) to a business error, so concurrent
     * requests for the same slot fail cleanly for everyone but the first.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function guarded(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23P01') {
                throw ShowingException::slotUnavailable();
            }
            throw $e;
        }
    }
}
