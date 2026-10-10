<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storage paths and disks are never exposed; downloads go through signed links.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category->value,
            'visibility' => $this->visibility,
            'retain_until' => $this->retain_until?->toDateString(),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'original_name' => $v->original_name,
                'mime_type' => $v->mime_type,
                'size' => $v->size,
                'sha256' => $v->sha256,
                'scan_status' => $v->scan_status->value,
                'uploaded_by' => $v->uploader?->full_name,
                'created_at' => $v->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
