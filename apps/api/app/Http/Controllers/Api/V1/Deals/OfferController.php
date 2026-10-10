<?php

namespace App\Http\Controllers\Api\V1\Deals;

use App\Application\Deals\OfferService;
use App\Domain\Deals\Enums\Contingency;
use App\Domain\Deals\Enums\Financing;
use App\Domain\Deals\Enums\OfferStatus;
use App\Domain\Identity\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Http\Resources\TransactionResource;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offers) {}

    /** Latest offer of each negotiation the user may see. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Offer::class);
        $f = $request->validate([
            'status' => ['nullable', Rule::enum(OfferStatus::class)],
            'property_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();
        $seeAll = $user->hasPermission(Permission::OffersViewAny) || $user->hasPermission(Permission::OffersManageAny);
        $myAgentIds = Agent::query()->where('user_id', $user->id)->pluck('id');

        $offers = Offer::query()
            ->whereDoesntHave('counters')
            ->with(['property', 'buyerAgent.user', 'transaction'])
            ->when(! $seeAll, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereIn('buyer_agent_id', $myAgentIds)
                ->orWhereHas('property', fn (Builder $p) => $p->whereIn('listing_agent_id', $myAgentIds))))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->latest('updated_at')
            ->paginate($f['per_page'] ?? 25)
            ->withQueryString();

        return OfferResource::collection($offers);
    }

    /** The whole negotiation (original offer and counters) in order. */
    public function show(Offer $offer): JsonResponse
    {
        Gate::authorize('view', $offer);
        $chain = [];
        $node = $offer->root();
        while ($node !== null) {
            $chain[] = $node->load(['property', 'buyerAgent.user', 'transaction']);
            $node = $node->counters()->latest('id')->first();
        }

        return response()->json(['data' => OfferResource::collection($chain)->resolve()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Offer::class);
        $data = $request->validate([
            'property_id' => ['required_without:property_slug', 'nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'property_slug' => ['required_without:property_id', 'nullable', 'string', Rule::exists('properties', 'slug')->whereNull('deleted_at')],
            'lead_id' => ['nullable', 'integer'],
            'buyer_agent_id' => ['nullable', 'integer'],
            'buyer_agent_external' => ['nullable', 'string', 'max:200'],
            'buyer_name' => ['required', 'string', 'max:200'],
            ...$this->termRules(true),
            'submit' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['lead_id'])) {
            Gate::authorize('view', Lead::query()->findOrFail($data['lead_id']));
        }
        $offer = $this->offers->create($request->user(), isset($data['property_id']) ? Property::query()->findOrFail($data['property_id']) : Property::query()->where('slug', $data['property_slug'])->firstOrFail(), $data, $request->boolean('submit'));

        return (new OfferResource($offer->load(['property', 'buyerAgent.user'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, Offer $offer): OfferResource
    {
        Gate::authorize('author', $offer);
        $data = $request->validate(['buyer_name' => ['sometimes', 'string', 'max:200'], 'buyer_agent_external' => ['nullable', 'string', 'max:200'], ...$this->termRules(false)]);

        return $this->fresh($this->offers->updateDraft($request->user(), $offer, $data));
    }

    public function submit(Request $request, Offer $offer): OfferResource
    {
        Gate::authorize('author', $offer);

        return $this->fresh($this->offers->submit($request->user(), $offer));
    }

    public function counter(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('respond', $offer);
        $data = $request->validate([...$this->termRules(false), 'note' => ['nullable', 'string', 'max:1000']]);
        $counter = $this->offers->counter($request->user(), $offer, $data);

        return (new OfferResource($counter->load(['property', 'buyerAgent.user'])))->response()->setStatusCode(201);
    }

    public function accept(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('respond', $offer);
        $transaction = $this->offers->accept($request->user(), $offer);

        return (new TransactionResource($transaction->load(['property', 'listingAgent.user', 'buyerAgent.user', 'participants', 'milestones'])))->response()->setStatusCode(201);
    }

    public function reject(Request $request, Offer $offer): OfferResource
    {
        Gate::authorize('respond', $offer);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->fresh($this->offers->reject($request->user(), $offer, $data['note'] ?? null));
    }

    public function withdraw(Request $request, Offer $offer): OfferResource
    {
        Gate::authorize('author', $offer);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->fresh($this->offers->withdraw($request->user(), $offer, $data['note'] ?? null));
    }

    private function fresh(Offer $offer): OfferResource
    {
        return new OfferResource($offer->refresh()->load(['property', 'buyerAgent.user', 'transaction']));
    }

    /**
     * @return array<string, mixed>
     */
    private function termRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'amount' => [$req, 'numeric', 'gt:0', 'max:999999999999'],
            'earnest_money' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'financing' => [$req, Rule::enum(Financing::class)],
            'down_payment_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'closing_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'contingencies' => ['nullable', 'array', 'max:10'],
            'contingencies.*' => ['string', 'distinct', Rule::enum(Contingency::class)],
            'terms' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
