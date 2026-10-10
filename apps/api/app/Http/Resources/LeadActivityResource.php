<?php

namespace App\Http\Resources;

use App\Models\LeadActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadActivity
 */
class LeadActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'body' => $this->body,
            'metadata' => $this->metadata,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'full_name' => $this->user->full_name] : null),
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
