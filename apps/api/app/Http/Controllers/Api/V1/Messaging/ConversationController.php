<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Application\Messaging\MessagingService;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Private conversations; only participants can read or post (no administrator override). */
class ConversationController extends Controller
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = Conversation::query()->forUser($user)
            ->with(['participants', 'property', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate((int) min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Conversation $c) => $this->summary($c, $user))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = Message::query()
            ->join('conversation_participants as cp', fn ($j) => $j->on('cp.conversation_id', '=', 'messages.conversation_id')->where('cp.user_id', $user->id))
            ->where('messages.user_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('cp.last_read_at')->orWhereColumn('messages.created_at', '>', 'cp.last_read_at'))
            ->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('messages.use');
        $data = $request->validate([
            'recipient_ids' => ['required_without_all:transaction_id,agent_slug', 'array', 'min:1', 'max:10'],
            'agent_slug' => ['nullable', 'string', 'max:120'],
            'recipient_ids.*' => ['integer', 'distinct'],
            'transaction_id' => ['nullable', 'integer'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'property_slug' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $transaction = null;
        $recipients = $data['recipient_ids'] ?? [];
        // Public pages know agents and listings by slug only.
        if (isset($data['agent_slug'])) {
            $recipients[] = Agent::query()->where('slug', $data['agent_slug'])->where('status', 'active')->firstOrFail()->user_id;
        }
        if (isset($data['property_slug'])) {
            $data['property_id'] = Property::query()->where('slug', $data['property_slug'])->value('id') ?? abort(404);
        }
        if (isset($data['transaction_id'])) {
            $transaction = Transaction::query()->findOrFail($data['transaction_id']);
            Gate::authorize('view', $transaction);
            // A deal's conversation always includes everyone on the deal, and only them.
            $recipients = $this->messaging->transactionUserIds($transaction);
        }

        $conversation = $this->messaging->start($request->user(), $recipients, $data['body'], $data['subject'] ?? null, $data['property_id'] ?? null, $transaction);

        return response()->json(['data' => $this->detail($conversation, $request->user())], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 404);
        $this->messaging->markRead($user, $conversation);

        return response()->json(['data' => $this->detail($conversation, $user)]);
    }

    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('messages.use');
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:5000']]);
        $message = $this->messaging->send($user, $conversation, $data['body']);

        return response()->json(['data' => $this->message($message->setRelation('author', $user), $user)], 201);
    }

    /** Agents a client may message (active agents of the brokerage), for the composer. */
    public function contacts(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $query = User::query()->where('id', '!=', $user->id)->where('status', 'active')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->whereRaw("(first_name || ' ' || last_name) ilike ?", ['%'.$q.'%'])->orWhere('email', 'ilike', $q.'%')));
        if (! $this->messaging->isProfessional($user)) {
            $query->whereHas('agentProfile', fn ($a) => $a->where('status', 'active'));
        } else {
            $query->where(fn ($w) => $w->whereHas('agentProfile', fn ($a) => $a->where('status', 'active'))
                ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['broker', 'office_manager'])));
        }

        return response()->json(['data' => $query->orderBy('first_name')->limit(20)->get()
            ->map(fn (User $u) => ['id' => $u->id, 'full_name' => $u->full_name])->all()]);
    }

    private function summary(Conversation $c, User $user): array
    {
        $last = $c->messages->first();

        return [
            'id' => $c->id,
            'subject' => $c->subject,
            'property' => $c->property ? ['slug' => $c->property->slug, 'title' => $c->property->title] : null,
            'transaction_id' => $c->transaction_id,
            'participants' => $c->participants->where('id', '!=', $user->id)->values()->map(fn (User $u) => ['id' => $u->id, 'full_name' => $u->full_name])->all(),
            'last_message' => $last ? ['body' => mb_strimwidth($last->body, 0, 140, '…'), 'mine' => $last->user_id === $user->id, 'created_at' => $last->created_at?->toIso8601String()] : null,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread' => $this->messaging->unreadCount($user, $c),
        ];
    }

    private function detail(Conversation $c, User $user): array
    {
        $c->load(['participants', 'property']);
        $messages = $c->messages()->with('author')->latest('id')->limit(200)->get()->reverse()->values();

        return [
            ...$this->summary($c->setRelation('messages', $messages->reverse()->take(1)->values()), $user),
            'messages' => $messages->map(fn (Message $m) => $this->message($m, $user))->all(),
        ];
    }

    private function message(Message $m, User $user): array
    {
        return [
            'id' => $m->id,
            'body' => $m->body,
            'author' => $m->author ? ['id' => $m->author->id, 'full_name' => $m->author->full_name] : null,
            'mine' => $m->user_id === $user->id,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
