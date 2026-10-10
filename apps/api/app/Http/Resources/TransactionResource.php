<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $manages = $user !== null && Gate::allows('manage', $this->resource);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'property' => $this->whenLoaded('property', fn () => $this->property ? [
                'id' => $this->property->id, 'slug' => $this->property->slug, 'title' => $this->property->title,
                'street_address' => $this->property->street_address, 'city' => $this->property->city, 'state' => $this->property->state,
            ] : null),
            'offer_id' => $this->offer_id,
            'sale_price' => (float) $this->sale_price,
            'contract_date' => $this->contract_date?->toDateString(),
            'closing_date' => $this->closing_date?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'listing_agent' => $this->whenLoaded('listingAgent', fn () => $this->listingAgent ? ['id' => $this->listingAgent->id, 'user_id' => $this->listingAgent->user_id, 'full_name' => $this->listingAgent->user?->full_name] : null),
            'buyer_agent' => $this->whenLoaded('buyerAgent', fn () => $this->buyerAgent ? ['id' => $this->buyerAgent->id, 'user_id' => $this->buyerAgent->user_id, 'full_name' => $this->buyerAgent->user?->full_name] : null),
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($p) => [
                'id' => $p->id, 'role' => $p->role->value, 'name' => $p->name, 'user_id' => $p->user_id,
                // Contact details are for the agents/managers working the deal.
                'email' => $manages ? $p->email : null,
                'phone' => $manages ? $p->phone : null,
            ])),
            'milestones' => $this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($m) => [
                'id' => $m->id, 'type' => $m->type->value, 'title' => $m->title, 'due_date' => $m->due_date?->toDateString(),
                'completed_at' => $m->completed_at?->toIso8601String(), 'notes' => $m->notes,
            ])),
            'can' => ['manage' => $manages],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
