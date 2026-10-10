<?php

namespace App\Http\Resources;

use App\Models\MlsConnection;
use App\Models\MlsProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes credential values — only which credential keys are stored.
 *
 * @mixin MlsProvider
 */
class MlsProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'driver' => $this->driver,
            'protocol' => $this->protocol,
            'status' => $this->status->value,
            'is_enabled' => $this->is_enabled,
            'website' => $this->website,
            'notes' => $this->notes,
            'display_rule' => $this->whenLoaded('displayRule', fn () => $this->displayRule?->only([
                'attribution_template', 'disclaimer', 'min_refresh_minutes', 'retention_days',
                'allowed_fields', 'photo_rules', 'allow_public_display', 'allow_mobile_display',
                'allow_ai_processing', 'hide_withdrawn_immediately',
            ])),
            'connections' => $this->whenLoaded('connections', fn () => $this->connections->map(fn (MlsConnection $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'environment' => $c->environment,
                'base_url' => $c->base_url,
                'token_url' => $c->token_url,
                'status' => $c->status,
                'credential_keys' => $c->credentialKeys(),
                'last_authenticated_at' => $c->last_authenticated_at?->toIso8601String(),
                'last_synced_at' => $c->last_synced_at?->toIso8601String(),
                'last_error' => $c->last_error,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
