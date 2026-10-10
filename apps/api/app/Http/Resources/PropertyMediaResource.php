<?php

namespace App\Http\Resources;

use App\Models\PropertyMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PropertyMedia
 */
class PropertyMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'url' => $this->publicUrl(),
            'mime_type' => $this->mime_type,
            'width' => $this->width,
            'height' => $this->height,
            'caption' => $this->caption,
            'sort_order' => $this->sort_order,
            'display_allowed' => $this->display_allowed,
        ];
    }
}
