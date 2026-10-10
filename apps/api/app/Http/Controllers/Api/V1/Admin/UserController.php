<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Identity\UserAdministration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\SyncUserRolesRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly UserAdministration $administration) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'sort' => ['nullable', Rule::in(['created_at', '-created_at', 'last_name', '-last_name', 'email', '-email'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sort = $filters['sort'] ?? '-created_at';

        $users = User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('lower(email) like ?', [$term])
                    ->orWhereRaw("lower(first_name || ' ' || last_name) like ?", [$term]));
            })
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $user = $this->administration->create($request->user(), collect($data)->except('roles')->all(), $data['roles']);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        return new UserResource($this->administration->update($request->user(), $user, $request->validated()));
    }

    public function syncRoles(SyncUserRolesRequest $request, User $user): UserResource
    {
        Gate::authorize('assignRoles', $user);

        return new UserResource($this->administration->syncRoles($request->user(), $user, $request->validated('roles')));
    }

    public function deactivate(Request $request, User $user): UserResource
    {
        Gate::authorize('deactivate', $user);

        return new UserResource($this->administration->deactivate($request->user(), $user));
    }

    public function reactivate(Request $request, User $user): UserResource
    {
        Gate::authorize('deactivate', $user);

        return new UserResource($this->administration->reactivate($request->user(), $user));
    }
}
