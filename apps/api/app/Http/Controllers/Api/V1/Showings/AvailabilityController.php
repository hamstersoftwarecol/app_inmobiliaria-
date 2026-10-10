<?php

namespace App\Http\Controllers\Api\V1\Showings;

use App\Domain\Identity\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTimeOff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The signed-in agent's weekly showing hours (agency local time) and time off. */
class AvailabilityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->agent($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'windows' => ['present', 'array', 'max:35'],
            'windows.*.weekday' => ['required', 'integer', 'between:1,7'],
            'windows.*.start_time' => ['required', 'date_format:H:i'],
            'windows.*.end_time' => ['required', 'date_format:H:i', 'after:windows.*.start_time'],
        ]);

        // Windows on the same day must not overlap.
        $byDay = collect($data['windows'])->groupBy('weekday');
        foreach ($byDay as $day => $windows) {
            $sorted = $windows->sortBy('start_time')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]['start_time'] < $sorted[$i - 1]['end_time']) {
                    throw ValidationException::withMessages(['windows' => "Hours overlap on weekday {$day}."]);
                }
            }
        }

        DB::transaction(function () use ($agent, $data) {
            $agent->availabilities()->delete();
            foreach ($data['windows'] as $w) {
                $agent->availabilities()->create(['weekday' => $w['weekday'], 'start_time' => $w['start_time'], 'end_time' => $w['end_time']]);
            }
        });

        return response()->json(['data' => $this->payload($agent)]);
    }

    public function storeTimeOff(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after:-1 day'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'before:+1 year'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $off = $agent->timeOff()->create([
            'starts_at' => Carbon::parse($data['starts_at'])->utc(),
            'ends_at' => Carbon::parse($data['ends_at'])->utc(),
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['data' => $this->timeOffPayload($off)], 201);
    }

    public function destroyTimeOff(Request $request, int $timeOff): Response
    {
        $this->agent($request)->timeOff()->whereKey($timeOff)->firstOrFail()->delete();

        return response()->noContent();
    }

    private function agent(Request $request): Agent
    {
        $user = $request->user();
        if (! $user->hasPermission(Permission::AppointmentsManageOwn) && ! $user->hasPermission(Permission::AppointmentsManageAny)) {
            throw new AuthorizationException;
        }

        return $user->agentProfile ?? abort(404, 'No agent profile.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Agent $agent): array
    {
        return [
            'timezone' => $agent->timezone(),
            'windows' => $agent->availabilities()->get()->map(fn ($w) => [
                'weekday' => $w->weekday,
                'start_time' => substr((string) $w->start_time, 0, 5),
                'end_time' => substr((string) $w->end_time, 0, 5),
            ])->values(),
            'time_off' => $agent->timeOff()->where('ends_at', '>', now())->get()->map(fn ($t) => $this->timeOffPayload($t))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timeOffPayload(AgentTimeOff $t): array
    {
        return ['id' => $t->id, 'starts_at' => $t->starts_at->toIso8601String(), 'ends_at' => $t->ends_at->toIso8601String(), 'reason' => $t->reason];
    }
}
