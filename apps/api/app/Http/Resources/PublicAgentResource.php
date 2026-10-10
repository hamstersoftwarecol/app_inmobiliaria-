<?php

namespace App\Http\Resources;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public directory card/profile. Exposes only data the agent opted to
 * publish plus the verified licence required for advertising.
 *
 * @mixin Agent
 */
class PublicAgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $license = $this->verifiedLicense;

        return [
            'slug' => $this->slug,
            'full_name' => $this->user->full_name,
            'first_name' => $this->user->first_name,
            'photo_url' => $this->photo_url,
            'email' => $this->public_email,
            'phone' => $this->public_phone,
            'bio' => $this->bio,
            'bio_es' => $this->bio_es,
            'languages' => $this->languages ?? [],
            'specialties' => $this->specialties ?? [],
            'agency' => ['name' => $this->agency->name, 'license_number' => $this->agency->license_number],
            'office' => $this->office && $this->office->is_active ? [
                'name' => $this->office->name,
                'street_address' => $this->office->street_address,
                'unit' => $this->office->unit,
                'city' => $this->office->city,
                'state' => $this->office->state,
                'postal_code' => $this->office->postal_code,
                'phone' => $this->office->phone,
            ] : null,
            'license' => $license ? [
                'type' => $license->license_type->value,
                'type_label' => $license->license_type->label(),
                'state' => $license->license_state,
                'number' => $license->license_number,
                'verified' => true,
            ] : null,
        ];
    }
}
