<?php

namespace App\Http\Resources;

use App\Application\Showings\AppointmentService;
use App\Domain\Showings\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Requesters see their own booking; hosts and managers also see the contact details, CRM link and
 * internal notes/feedback.
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $service = app(AppointmentService::class);
        $manages = $user !== null && $service->canManage($user, $this->resource);
        $isRequester = $user !== null && $this->requested_by === $user->id;
        $live = $this->status->isLive();
        $p = $this->property;
        $showAddress = $p !== null && ($p->address_display || $manages || $this->status === AppointmentStatus::Confirmed);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'timezone' => $this->timezone,
            'property' => $p ? [
                'slug' => $p->slug,
                'title' => $p->title,
                'street_address' => $showAddress ? $p->street_address : null,
                'city' => $p->city,
                'state' => $p->state,
            ] : null,
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'full_name' => $this->agent->user?->full_name,
                'phone' => $this->agent->public_phone,
            ]),
            'contact' => $manages ? ['name' => $this->contact_name, 'email' => $this->contact_email, 'phone' => $this->contact_phone] : null,
            'lead_id' => $manages ? $this->lead_id : null,
            'notes' => $this->notes,
            'status_reason' => $this->status_reason,
            'feedback' => $manages ? ['rating' => $this->feedback_rating, 'comment' => $this->feedback_comment] : null,
            'can' => [
                'confirm' => $manages && $this->status === AppointmentStatus::Requested,
                'decline' => $manages && $this->status === AppointmentStatus::Requested,
                'cancel' => ($manages || $isRequester) && $live,
                'reschedule' => ($manages || $isRequester) && $live && $this->starts_at->isFuture(),
                'complete' => $manages && $this->status === AppointmentStatus::Confirmed && $this->starts_at->isPast(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
