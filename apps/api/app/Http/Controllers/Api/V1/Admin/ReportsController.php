<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Audit\AuditLogger;
use App\Application\Reports\AgencyDashboard;
use App\Application\Reports\CsvExport;
use App\Domain\Audit\AuditCategory;
use App\Domain\Shared\DomainException;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Agency KPI dashboard and CSV exports (staff with `reports.view`; MFA enforced by the route group). */
class ReportsController extends Controller
{
    public function dashboard(Request $request, AgencyDashboard $dashboard): JsonResponse
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->period($request);

        return response()->json(['data' => $dashboard->summary($from, $to)]);
    }

    public function export(Request $request, string $dataset, CsvExport $exports, AuditLogger $audit): StreamedResponse
    {
        validator(['dataset' => $dataset], ['dataset' => ['required', Rule::in(CsvExport::DATASETS)]])->validate();
        if (! $exports->allowed($request->user(), $dataset)) {
            throw new AuthorizationException;
        }
        [$from, $to] = $this->period($request);
        // Exports leave the platform: record who took what.
        $audit->log(AuditCategory::Data, 'reports.exported', $request->user(), null, null, null, [
            'dataset' => $dataset, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
        ]);

        return $exports->download($dataset, $from, $to);
    }

    /**
     * Agency calendar days; defaults to the last 30 days; at most one year.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $zone = (string) config('app_inmobiliaria.agency_timezone');
        $to = isset($data['to']) ? Carbon::parse($data['to'], $zone)->endOfDay() : Carbon::now($zone)->endOfDay();
        $from = isset($data['from']) ? Carbon::parse($data['from'], $zone)->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        if ($from->diffInDays($to) > 366) {
            throw new DomainException('range_too_large', 'Choose a period of at most one year.', 422);
        }

        return [$from, $to];
    }
}
