<?php

namespace App\Http\Resources;

use App\Models\AgentLicense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentLicense
 */
class AgentLicenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'license_number' => $this->license_number,
            'license_state' => $this->license_state,
            'license_type' => $this->license_type->value,
            'license_type_label' => $this->license_type->label(),
            'status' => $this->status->value,
            'is_currently_valid' => $this->isCurrentlyValid(),
            'issued_on' => $this->issued_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by' => $this->whenLoaded('verifier', fn () => $this->verifier ? ['id' => $this->verifier->id, 'full_name' => $this->verifier->full_name] : null),
            'verification_source' => $this->verification_source?->value,
            'verification_reference' => $this->verification_reference,
            'verification_notes' => $this->verification_notes,
            'status_reason' => $this->status_reason,
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'full_name' => $this->agent->user?->full_name,
                'email' => $this->agent->user?->email,
                'user_id' => $this->agent->user_id,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
