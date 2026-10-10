<?php

namespace App\Http\Resources;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agency
 */
class AgencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'slug' => $this->slug,
            'license_number' => $this->license_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'timezone' => $this->timezone,
            'offices_count' => $this->whenCounted('offices'),
            'agents_count' => $this->whenCounted('agents'),
            'offices' => OfficeResource::collection($this->whenLoaded('offices')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
