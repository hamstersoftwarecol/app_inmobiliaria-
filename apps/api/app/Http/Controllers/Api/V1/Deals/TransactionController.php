<?php

namespace App\Http\Controllers\Api\V1\Deals;

use App\Application\Audit\AuditLogger;
use App\Application\Deals\TransactionService;
use App\Domain\Audit\AuditCategory;
use App\Domain\Deals\Enums\MilestoneType;
use App\Domain\Deals\Enums\ParticipantRole;
use App\Domain\Deals\Enums\TransactionStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\TransactionMilestone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    private const RELATIONS = ['property', 'listingAgent.user', 'buyerAgent.user', 'participants', 'milestones'];

    public function __construct(private readonly TransactionService $transactions, private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::enum(TransactionStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $list = Transaction::query()->visibleTo($request->user())->with(self::RELATIONS)
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("status = 'open' desc")->orderBy('closing_date')
            ->paginate($f['per_page'] ?? 25)->withQueryString();

        return TransactionResource::collection($list);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('view', $transaction);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function update(Request $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('manage', $transaction);
        $this->transactions->requireOpen($transaction);
        $data = $request->validate(['closing_date' => ['nullable', 'date_format:Y-m-d']]);
        $before = $transaction->closing_date?->toDateString();
        $transaction->fill($data)->save();
        $this->audit->log(AuditCategory::Data, 'transactions.updated', $request->user(), $transaction, ['closing_date' => $before], ['closing_date' => $transaction->closing_date?->toDateString()]);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function close(Request $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('manage', $transaction);
        $data = $request->validate(['closing_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);

        return new TransactionResource($this->transactions->close($request->user(), $transaction, $data['closing_date'] ?? null)->load(self::RELATIONS));
    }

    public function cancel(Request $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('manage', $transaction);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);

        return new TransactionResource($this->transactions->cancel($request->user(), $transaction, $data['reason'])->load(self::RELATIONS));
    }

    public function storeMilestone(Request $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('manage', $transaction);
        $this->transactions->requireOpen($transaction);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'due_date' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $transaction->milestones()->create([...$data, 'type' => MilestoneType::Custom, 'sort_order' => (int) $transaction->milestones()->max('sort_order') + 1]);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function updateMilestone(Request $request, Transaction $transaction, TransactionMilestone $milestone): TransactionResource
    {
        Gate::authorize('manage', $transaction);
        abort_unless($milestone->transaction_id === $transaction->id, 404);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'completed' => ['sometimes', 'boolean'],
        ]);
        $milestone->fill(collect($data)->only(['title', 'due_date', 'notes'])->all());
        if (array_key_exists('due_date', $data)) {
            $milestone->reminder_sent_at = null;
        }
        if (array_key_exists('completed', $data)) {
            $milestone->forceFill($data['completed']
                ? ['completed_at' => $milestone->completed_at ?? now(), 'completed_by' => $request->user()->id]
                : ['completed_at' => null, 'completed_by' => null]);
        }
        $milestone->save();

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function destroyMilestone(Transaction $transaction, TransactionMilestone $milestone): Response
    {
        Gate::authorize('manage', $transaction);
        abort_unless($milestone->transaction_id === $transaction->id && $milestone->type === MilestoneType::Custom, 404);
        $milestone->delete();

        return response()->noContent();
    }

    /** Clients get access (shared documents, messages) when their e-mail matches an active account. */
    public function storeParticipant(Request $request, Transaction $transaction): TransactionResource
    {
        Gate::authorize('manage', $transaction);
        $data = $request->validate([
            'role' => ['required', Rule::enum(ParticipantRole::class)],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
        ]);
        $userId = isset($data['email'])
            ? User::query()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->where('status', UserStatus::Active->value)->value('id')
            : null;
        if ($userId !== null && $transaction->participants()->where('user_id', $userId)->exists()) {
            $userId = null;
        }
        $participant = $transaction->participants()->create([...$data, 'user_id' => $userId]);
        $this->audit->log(AuditCategory::Data, 'transactions.participant_added', $request->user(), $transaction, null, ['participant_id' => $participant->id, 'role' => $data['role'], 'linked_user' => $userId !== null]);

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function destroyParticipant(Request $request, Transaction $transaction, int $participant): Response
    {
        Gate::authorize('manage', $transaction);
        $transaction->participants()->whereKey($participant)->firstOrFail()->delete();
        $this->audit->log(AuditCategory::Data, 'transactions.participant_removed', $request->user(), $transaction, ['participant_id' => $participant]);

        return response()->noContent();
    }
}
