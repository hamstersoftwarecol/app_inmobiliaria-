<?php

namespace App\Http\Resources;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Internal representation (staff and the agent themself).
 *
 * @mixin Agent
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $verified = $this->relationLoaded('verifiedLicense') ? $this->verifiedLicense : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'email' => $this->user->email,
                'status' => $this->user->status->value,
            ]),
            'agency' => $this->whenLoaded('agency', fn () => ['id' => $this->agency->id, 'name' => $this->agency->name]),
            'office' => $this->whenLoaded('office', fn () => $this->office ? ['id' => $this->office->id, 'name' => $this->office->name, 'city' => $this->office->city] : null),
            'status' => $this->status->value,
            'is_public' => $this->is_public,
            'is_publicly_listed' => $this->when(isset($this->is_publicly_listed), fn () => (bool) $this->is_publicly_listed),
            'public_email' => $this->public_email,
            'public_phone' => $this->public_phone,
            'bio' => $this->bio,
            'bio_es' => $this->bio_es,
            'languages' => $this->languages ?? [],
            'specialties' => $this->specialties ?? [],
            'photo_url' => $this->photo_url,
            'joined_on' => $this->joined_on?->toDateString(),
            'license_verified' => $verified !== null,
            'verified_license' => $verified ? new AgentLicenseResource($verified) : null,
            'licenses' => AgentLicenseResource::collection($this->whenLoaded('licenses')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
