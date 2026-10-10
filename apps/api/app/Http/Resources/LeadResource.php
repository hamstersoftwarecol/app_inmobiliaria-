<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Staff-only (leads contain personal data and never appear in public responses).
 *
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'intent' => $this->intent?->value,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'budget_min' => $this->budget_min !== null ? (float) $this->budget_min : null,
            'budget_max' => $this->budget_max !== null ? (float) $this->budget_max : null,
            'preferences' => $this->preferences ?? (object) [],
            'preferred_language' => $this->preferred_language,
            'contact_consent' => $this->contact_consent,
            'marketing_consent' => $this->marketing_consent,
            'consent_at' => $this->consent_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'full_name' => $this->assignedAgent->user?->full_name,
                'user_id' => $this->assignedAgent->user_id,
            ] : null),
            'property' => $this->whenLoaded('property', fn () => $this->property ? [
                'id' => $this->property->id, 'slug' => $this->property->slug, 'title' => $this->property->title,
            ] : null),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'first_contacted_at' => $this->first_contacted_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'activities' => LeadActivityResource::collection($this->whenLoaded('activities')),
            'tasks' => CrmTaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
