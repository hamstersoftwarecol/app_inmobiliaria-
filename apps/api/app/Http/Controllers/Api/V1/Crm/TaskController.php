<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Domain\Identity\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CrmTaskRequest;
use App\Http\Resources\CrmTaskResource;
use App\Models\CrmTask;
use App\Models\Lead;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Follow-up tasks and reminders. Only lead managers (`leads.assign`) assign tasks to others. */
class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CrmTask::class);
        $f = $request->validate([
            'state' => ['nullable', Rule::in(['open', 'overdue', 'completed'])],
            'scope' => ['nullable', Rule::in(['mine', 'all'])],
            'lead_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();
        $all = ($f['scope'] ?? 'mine') === 'all';
        if ($all && ! $user->hasPermission(Permission::LeadsAssign)) {
            throw new AuthorizationException;
        }

        $tasks = CrmTask::query()
            ->with(['lead', 'assignee'])
            ->when(! $all, fn ($q) => $q->where('assigned_to', $user->id))
            ->when($f['lead_id'] ?? null, fn ($q, $v) => $q->where('lead_id', $v))
            ->when(($f['state'] ?? 'open') === 'open', fn ($q) => $q->whereNull('completed_at'))
            ->when(($f['state'] ?? null) === 'overdue', fn ($q) => $q->whereNull('completed_at')->where('due_at', '<', now()))
            ->when(($f['state'] ?? null) === 'completed', fn ($q) => $q->whereNotNull('completed_at'))
            ->orderBy(($f['state'] ?? null) === 'completed' ? 'completed_at' : 'due_at', ($f['state'] ?? null) === 'completed' ? 'desc' : 'asc')
            ->paginate($f['per_page'] ?? 50)
            ->withQueryString();

        return CrmTaskResource::collection($tasks);
    }

    public function store(CrmTaskRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmTask::class);
        $data = $request->validated();
        $user = $request->user();

        if (isset($data['lead_id'])) {
            Gate::authorize('view', Lead::query()->findOrFail($data['lead_id']));
        }
        $assignee = $data['assigned_to'] ?? $user->id;
        if ($assignee !== $user->id && ! $user->hasPermission(Permission::LeadsAssign)) {
            throw new AuthorizationException('Only lead managers can assign tasks to other people.');
        }

        $task = CrmTask::query()->create([
            'lead_id' => $data['lead_id'] ?? null,
            'assigned_to' => $assignee,
            'created_by' => $user->id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'due_at' => Carbon::parse($data['due_at'])->utc(),
        ]);

        return (new CrmTaskResource($task->load(['lead', 'assignee'])))->response()->setStatusCode(201);
    }

    public function update(CrmTaskRequest $request, CrmTask $task): CrmTaskResource
    {
        Gate::authorize('manage', $task);
        $data = $request->validated();
        $user = $request->user();

        if (isset($data['assigned_to']) && $data['assigned_to'] !== $task->assigned_to && ! $user->hasPermission(Permission::LeadsAssign)) {
            throw new AuthorizationException('Only lead managers can reassign tasks.');
        }
        $task->fill(collect($data)->only(['title', 'notes', 'assigned_to'])->all());
        if (isset($data['due_at'])) {
            $task->due_at = Carbon::parse($data['due_at'])->utc();
            $task->reminder_sent_at = null; // rescheduled: remind again
        }
        if (array_key_exists('completed', $data)) {
            $task->completed_at = $data['completed'] ? ($task->completed_at ?? now()) : null;
        }
        $task->save();

        return new CrmTaskResource($task->load(['lead', 'assignee']));
    }

    public function destroy(CrmTask $task): Response
    {
        Gate::authorize('manage', $task);
        $task->delete();

        return response()->noContent();
    }
}
