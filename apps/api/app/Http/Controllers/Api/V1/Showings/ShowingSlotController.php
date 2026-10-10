<?php

namespace App\Http\Controllers\Api\V1\Showings;

use App\Application\Showings\AppointmentService;
use App\Application\Showings\ShowingSlots;
use App\Http\Controllers\Concerns\ResolvesClientChannel;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Public availability for a listing's showings (no personal data, only free slots). */
class ShowingSlotController extends Controller
{
    use ResolvesClientChannel;

    public function index(Request $request, string $slug, AppointmentService $appointments, ShowingSlots $slots): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
        ]);
        $property = Property::query()->publiclyVisible($this->channel($request))->where('slug', $slug)->firstOrFail();
        $agent = $appointments->hostFor($property);
        if ($agent === null) {
            return response()->json(['data' => ['bookable' => false, 'timezone' => null, 'duration_minutes' => null, 'days' => []]]);
        }

        $tz = $agent->timezone();
        $today = Carbon::now($tz)->startOfDay();
        $from = isset($data['from']) ? Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->startOfDay() : $today;
        if ($from->lt($today)) {
            $from = $today;
        }
        $days = [];
        for ($i = 0; $i < ($data['days'] ?? 7); $i++) {
            $date = $from->copy()->addDays($i)->format('Y-m-d');
            $days[] = [
                'date' => $date,
                'slots' => array_map(fn ($s) => ['starts_at' => $s['starts_at']->toIso8601String(), 'ends_at' => $s['ends_at']->toIso8601String()], $slots->forDate($agent, $date)),
            ];
        }

        return response()->json(['data' => [
            'bookable' => true,
            'timezone' => $tz,
            'duration_minutes' => (int) config('hitlease.showings.duration_minutes', 30),
            'days' => $days,
        ]]);
    }
}
