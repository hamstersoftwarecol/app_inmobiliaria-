<?php

namespace App\Http\Controllers\Api\V1\Showings;

use App\Application\Showings\AppointmentService;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Showings\Enums\AppointmentStatus;
use App\Http\Controllers\Concerns\ResolvesClientChannel;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Agent;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    use ResolvesClientChannel;

    public function __construct(private readonly AppointmentService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'when' => ['nullable', Rule::in(['upcoming', 'past'])],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'role' => ['nullable', Rule::in(['requester', 'host'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();
        $upcoming = ($f['when'] ?? 'upcoming') === 'upcoming';

        $list = Appointment::query()
            ->visibleTo($user)
            ->with(['property', 'agent.user'])
            ->when(($f['role'] ?? null) === 'requester', fn ($q) => $q->where('requested_by', $user->id))
            ->when(($f['role'] ?? null) === 'host', fn ($q) => $q->whereHas('agent', fn ($a) => $a->where('user_id', $user->id)))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($upcoming, fn ($q) => $q->where('ends_at', '>=', now())->orderBy('starts_at'))
            ->when(! $upcoming, fn ($q) => $q->where('ends_at', '<', now())->orderByDesc('starts_at'))
            ->paginate($f['per_page'] ?? 25)
            ->withQueryString();

        return AppointmentResource::collection($list);
    }

    public function show(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorizeView($request, $appointment);

        return new AppointmentResource($appointment->load(['property', 'agent.user']));
    }

    /** A visitor asks for a published slot (`appointments.request`). Honors `Idempotency-Key`. */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::AppointmentsRequest->value);
        $data = $request->validate([
            'property_slug' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9-]+$/'],
            'starts_at' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'contact_consent' => ['accepted'],
        ]);
        $property = Property::query()->publiclyVisible($this->channel($request))->where('slug', $data['property_slug'])->firstOrFail();

        $appointment = $this->service->request($request->user(), $property, Carbon::parse($data['starts_at'])->utc(), $data['notes'] ?? null, $data['phone'] ?? null, $request->ip());

        return (new AppointmentResource($appointment->load(['property', 'agent.user'])))->response()->setStatusCode(201);
    }

    /** Agents book on their own calendar; managers on anyone's. Confirmed immediately. */
    public function book(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'agent_id' => ['nullable', 'integer'],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'lead_id' => ['nullable', 'integer'],
            'contact_name' => ['required_without:lead_id', 'nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,20}$/'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $manageAny = $user->hasPermission(Permission::AppointmentsManageAny);
        if ($manageAny && isset($data['agent_id'])) {
            $agent = Agent::query()->where('status', 'active')->findOrFail($data['agent_id']);
        } elseif ($user->hasPermission(Permission::AppointmentsManageOwn) || $manageAny) {
            $agent = $user->agentProfile ?? throw new AuthorizationException('Your account has no agent profile.');
        } else {
            throw new AuthorizationException;
        }
        if (isset($data['lead_id'])) {
            Gate::authorize('view', Lead::query()->findOrFail($data['lead_id']));
        }

        $appointment = $this->service->book($user, $agent, $data);

        return (new AppointmentResource($appointment->load(['property', 'agent.user'])))->response()->setStatusCode(201);
    }

    public function confirm(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorizeManage($request, $appointment);

        return $this->fresh($this->service->confirm($request->user(), $appointment));
    }

    public function decline(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorizeManage($request, $appointment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->fresh($this->service->decline($request->user(), $appointment, $data['reason']));
    }

    public function cancel(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorizeParticipant($request, $appointment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->fresh($this->service->cancel($request->user(), $appointment, $data['reason']));
    }

    public function reschedule(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorizeParticipant($request, $appointment);
        $data = $request->validate(['starts_at' => ['required', 'date']]);

        return $this->fresh($this->service->reschedule($request->user(), $appointment, Carbon::parse($data['starts_at'])->utc()));
    }

    public function complete(Request $request, Appointment $appointment): AppointmentResource
    {
        $this->authorizeManage($request, $appointment);
        $data = $request->validate([
            'outcome' => ['required', Rule::in([AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])],
            'feedback_rating' => ['nullable', 'integer', 'between:1,5'],
            'feedback_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->fresh($this->service->complete($request->user(), $appointment, AppointmentStatus::from($data['outcome']), $data['feedback_rating'] ?? null, $data['feedback_comment'] ?? null));
    }

    private function fresh(Appointment $a): AppointmentResource
    {
        return new AppointmentResource($a->refresh()->load(['property', 'agent.user']));
    }

    private function authorizeView(Request $request, Appointment $a): void
    {
        $user = $request->user();
        if (! ($a->requested_by === $user->id || $user->hasPermission(Permission::AppointmentsViewAny) || $this->service->hosts($user, $a))) {
            throw new AuthorizationException;
        }
    }

    private function authorizeManage(Request $request, Appointment $a): void
    {
        if (! $this->service->canManage($request->user(), $a)) {
            throw new AuthorizationException;
        }
    }

    private function authorizeParticipant(Request $request, Appointment $a): void
    {
        $user = $request->user();
        if (! ($a->requested_by === $user->id || $this->service->canManage($user, $a))) {
            throw new AuthorizationException;
        }
    }
}
