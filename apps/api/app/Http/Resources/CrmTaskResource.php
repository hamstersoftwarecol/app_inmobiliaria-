<?php

namespace App\Http\Resources;

use App\Models\CrmTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmTask
 */
class CrmTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'due_at' => $this->due_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->completed_at === null && $this->due_at->isPast(),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? ['id' => $this->lead->id, 'full_name' => $this->lead->full_name] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => ['id' => $this->assignee->id, 'full_name' => $this->assignee->full_name]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
