<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\Crm\CrmReports;
use App\Domain\Crm\Exceptions\CrmException;
use App\Domain\Identity\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /** Agency-wide figures: `reports.view` with `leads.view_any`. Dates are agency calendar days. */
    public function summary(Request $request, CrmReports $reports): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission(Permission::ReportsView) || ! $user->hasPermission(Permission::LeadsViewAny)) {
            throw new AuthorizationException;
        }
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $zone = config('app_inmobiliaria.agency_timezone');
        $to = isset($data['to']) ? Carbon::parse($data['to'], $zone)->endOfDay() : Carbon::now($zone)->endOfDay();
        $from = isset($data['from']) ? Carbon::parse($data['from'], $zone)->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        if ($from->diffInDays($to) > 366) {
            throw CrmException::rangeTooLarge();
        }

        return response()->json(['data' => $reports->summary($from->utc(), $to->utc())]);
    }
}
