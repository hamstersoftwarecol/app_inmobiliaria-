<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\Crm\LeadService;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\LeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public const BOARD_LIMIT = 25;

    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lead::class);
        $f = $request->validate($this->filterRules() + [
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $leads = $this->filtered($request, $f)
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('last_activity_at')->orderByDesc('id')
            ->paginate($f['per_page'] ?? 25)
            ->withQueryString();

        return LeadResource::collection($leads);
    }

    /** Kanban columns: totals per stage and the most recently active leads of each. */
    public function board(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);
        $f = $request->validate($this->filterRules());

        $columns = collect(LeadStatus::cases())->map(function (LeadStatus $status) use ($request, $f) {
            $query = $this->filtered($request, $f)->where('status', $status->value);

            return [
                'status' => $status->value,
                'total' => (clone $query)->count(),
                'leads' => LeadResource::collection(
                    $query->orderByDesc('last_activity_at')->orderByDesc('id')->limit(self::BOARD_LIMIT)->get(),
                )->resolve(),
            ];
        });

        return response()->json(['data' => $columns->values()]);
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);
        $lead->load(['assignedAgent.user', 'property', 'activities.user', 'tasks' => fn ($q) => $q->with('assignee')->orderBy('due_at')]);

        $duplicates = $this->leads->duplicatesOf($lead)
            ->filter(fn (Lead $d) => Gate::allows('view', $d))
            ->map(fn (Lead $d) => ['id' => $d->id, 'full_name' => $d->full_name, 'status' => $d->status->value, 'created_at' => $d->created_at?->toIso8601String()])
            ->values();

        return (new LeadResource($lead))->additional(['meta' => [
            'possible_duplicates' => $duplicates,
            'can' => [
                'update' => Gate::allows('update', $lead),
                'assign' => Gate::allows('assign', $lead),
                'merge' => Gate::allows('merge', $lead),
                'delete' => Gate::allows('delete', $lead),
            ],
        ]])->response();
    }

    public function store(LeadRequest $request): JsonResponse
    {
        Gate::authorize('create', Lead::class);
        $lead = $this->leads->create($request->user(), $request->validated());

        return (new LeadResource($lead->load(['assignedAgent.user', 'property'])))->response()->setStatusCode(201);
    }

    public function update(LeadRequest $request, Lead $lead): LeadResource
    {
        Gate::authorize('update', $lead);

        return new LeadResource($this->leads->update($lead, $request->validated())->load(['assignedAgent.user', 'property']));
    }

    public function status(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('update', $lead);
        $data = $request->validate([
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', 'min:3', 'max:500'],
        ]);

        $this->leads->changeStatus($request->user(), $lead, LeadStatus::from($data['status']), $data['lost_reason'] ?? null);

        return new LeadResource($lead->load(['assignedAgent.user', 'property']));
    }

    public function assign(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('assign', $lead);
        $data = $request->validate(['agent_id' => ['present', 'nullable', 'integer']]);

        $this->leads->assign($request->user(), $lead, $data['agent_id']);

        return new LeadResource($lead->load(['assignedAgent.user', 'property']));
    }

    public function merge(Request $request, Lead $lead): LeadResource
    {
        $data = $request->validate(['into_lead_id' => ['required', 'integer']]);
        $target = Lead::query()->findOrFail($data['into_lead_id']);
        Gate::authorize('merge', $lead);
        Gate::authorize('update', $target);

        return new LeadResource($this->leads->merge($request->user(), $lead, $target)->load(['assignedAgent.user', 'property']));
    }

    public function destroy(Request $request, Lead $lead): Response
    {
        Gate::authorize('delete', $lead);
        $this->leads->delete($request->user(), $lead);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'assigned' => ['nullable', 'string', 'regex:/^(me|unassigned|\d+)$/'],
            'open' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<Lead>
     */
    private function filtered(Request $request, array $f): Builder
    {
        $user = $request->user();

        return Lead::query()
            ->visibleTo($user)
            ->with(['assignedAgent.user', 'property'])
            ->when($f['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';
                $digits = preg_replace('/\D+/', '', $search);
                $q->where(fn ($w) => $w
                    ->whereRaw("lower(first_name || ' ' || coalesce(last_name, '')) like ?", [$term])
                    ->orWhere('email_key', 'like', $term)
                    ->when(strlen((string) $digits) >= 4, fn ($p) => $p->orWhere('phone_key', 'like', '%'.$digits.'%')));
            })
            ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when(array_key_exists('open', $f) && $f['open'] !== null, fn ($q) => $request->boolean('open')
                ? $q->whereIn('status', LeadStatus::openValues())
                : $q->whereNotIn('status', LeadStatus::openValues()))
            ->when($f['assigned'] ?? null, fn ($q, $v) => match (true) {
                $v === 'me' => $q->whereHas('assignedAgent', fn ($a) => $a->where('user_id', $user->id)),
                $v === 'unassigned' => $q->whereNull('assigned_agent_id'),
                default => $q->where('assigned_agent_id', (int) $v),
            });
    }
}
