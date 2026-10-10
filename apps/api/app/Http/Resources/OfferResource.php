<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'side' => $this->side,
            'status' => $this->status->value,
            'property' => $this->whenLoaded('property', fn () => $this->property ? ['id' => $this->property->id, 'slug' => $this->property->slug, 'title' => $this->property->title, 'list_price' => $this->property->list_price !== null ? (float) $this->property->list_price : null] : null),
            'buyer_name' => $this->buyer_name,
            'buyer_agent' => $this->whenLoaded('buyerAgent', fn () => $this->buyerAgent ? ['id' => $this->buyerAgent->id, 'full_name' => $this->buyerAgent->user?->full_name] : null),
            'buyer_agent_external' => $this->buyer_agent_external,
            'lead_id' => $this->lead_id,
            'amount' => (float) $this->amount,
            'earnest_money' => $this->earnest_money !== null ? (float) $this->earnest_money : null,
            'financing' => $this->financing->value,
            'down_payment_percent' => $this->down_payment_percent !== null ? (float) $this->down_payment_percent : null,
            'closing_date' => $this->closing_date?->toDateString(),
            'contingencies' => $this->contingencies ?? [],
            'terms' => $this->terms,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'response_note' => $this->response_note,
            'transaction_id' => $this->whenLoaded('transaction', fn () => $this->transaction?->id),
            'can' => $request->user() ? [
                'edit' => $this->status->value === 'draft' && Gate::allows('author', $this->resource),
                'submit' => $this->status->value === 'draft' && Gate::allows('author', $this->resource),
                'withdraw' => in_array($this->status->value, ['draft', 'submitted'], true) && Gate::allows('author', $this->resource),
                'respond' => $this->status->value === 'submitted' && Gate::allows('respond', $this->resource),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
