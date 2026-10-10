<?php

namespace App\Application\Reports;

use App\Domain\Agency\Enums\LicenseStatus;
use App\Domain\Crm\Enums\LeadStatus;
use App\Domain\Mls\Enums\ListingStatus;
use App\Support\AgencyClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agency KPIs for a period of agency calendar days: inventory, agents, leads, showings, offers and
 * transactions, plus daily (≤ 62 days) or weekly time series. All figures come from the database;
 * nothing is estimated.
 */
final class AgencyDashboard
{
    /**
     * @param  Carbon  $from  start of the first agency day (any zone)
     * @param  Carbon  $to  end of the last agency day (any zone)
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $zone = (string) config('app_inmobiliaria.agency_timezone');
        $range = [$from->copy()->utc(), $to->copy()->utc()];
        $count = fn (string $table, string $column, array $where = []) => DB::table($table)
            ->whereBetween($column, $range)
            ->when(in_array($table, ['leads', 'properties'], true), fn ($q) => $q->whereNull('deleted_at'))
            ->where($where)->count();

        $listings = DB::table('properties')->whereNull('deleted_at')->where('is_published', true);
        $leadsCreated = $count('leads', 'created_at');
        $leadsWon = $count('leads', 'created_at', [['status', '=', LeadStatus::Won->value]]);
        $offersSubmitted = $count('offers', 'submitted_at', [['parent_id', '=', null]]);
        $offersAccepted = DB::table('offers')->whereNull('parent_id')->whereBetween('submitted_at', $range)
            ->where(fn ($q) => $q->whereIn('status', ['accepted', 'closed', 'terminated'])
                ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('offers as c')->whereColumn('c.parent_id', 'offers.id')->whereIn('c.status', ['accepted', 'closed', 'terminated'])))
            ->count();
        $showings = DB::table('appointments')->whereBetween('starts_at', $range)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $closed = DB::table('transactions')->where('status', 'closed')->whereBetween('closed_at', $range);

        return [
            'from' => $from->copy()->setTimezone($zone)->toDateString(),
            'to' => $to->copy()->setTimezone($zone)->toDateString(),
            'timezone' => $zone,
            'listings' => [
                'published' => (clone $listings)->count(),
                'active' => (clone $listings)->whereIn('status', [ListingStatus::Active->value, ListingStatus::ComingSoon->value])->count(),
                'under_contract' => (clone $listings)->whereIn('status', [ListingStatus::ActiveUnderContract->value, ListingStatus::Pending->value])->count(),
                'internal' => (clone $listings)->where('source', 'internal')->count(),
                'mls' => (clone $listings)->where('source', 'mls')->count(),
                'new_in_period' => (clone $listings)->whereBetween('published_at', $range)->count(),
                'median_active_price' => $this->round((clone $listings)->where('status', ListingStatus::Active->value)->where('listing_type', 'sale')
                    ->selectRaw('percentile_cont(0.5) within group (order by list_price) as m')->value('m'), 0),
            ],
            'agents' => [
                'active' => DB::table('agents')->whereNull('deleted_at')->where('status', 'active')->count(),
                'licences_pending' => DB::table('agent_licenses')->where('status', LicenseStatus::PendingVerification->value)->count(),
                'licences_expiring_30d' => DB::table('agent_licenses')->where('status', LicenseStatus::Verified->value)
                    ->whereBetween('expires_on', [AgencyClock::todayString(), AgencyClock::today()->addDays(30)->toDateString()])->count(),
            ],
            'leads' => [
                'created' => $leadsCreated,
                'won' => $leadsWon,
                'conversion_rate' => $leadsCreated > 0 ? round($leadsWon / $leadsCreated, 4) : null,
                'unassigned_open' => DB::table('leads')->whereNull('deleted_at')->whereNull('assigned_agent_id')->whereIn('status', LeadStatus::openValues())->count(),
            ],
            'showings' => [
                'scheduled' => (int) $showings->sum(),
                'completed' => (int) ($showings['completed'] ?? 0),
                'no_show' => (int) ($showings['no_show'] ?? 0),
                'cancelled_or_declined' => (int) (($showings['cancelled'] ?? 0) + ($showings['declined'] ?? 0)),
            ],
            'offers' => [
                'submitted' => $offersSubmitted,
                'accepted' => $offersAccepted,
                'acceptance_rate' => $offersSubmitted > 0 ? round($offersAccepted / $offersSubmitted, 4) : null,
            ],
            'transactions' => [
                'opened' => $count('transactions', 'created_at'),
                'closed' => (clone $closed)->count(),
                'cancelled' => $count('transactions', 'cancelled_at'),
                'closed_volume' => (float) ((clone $closed)->sum('sale_price')),
                'open' => DB::table('transactions')->where('status', 'open')->count(),
                'open_volume' => (float) DB::table('transactions')->where('status', 'open')->sum('sale_price'),
            ],
            'series' => $this->series($from, $to, $zone),
        ];
    }

    /**
     * Counts per agency day (or ISO week for long ranges), zero-filled.
     *
     * @return array{interval: string, points: list<array<string, int|string>>}
     */
    private function series(Carbon $from, Carbon $to, string $zone): array
    {
        $localFrom = $from->copy()->setTimezone($zone)->startOfDay();
        $localTo = $to->copy()->setTimezone($zone)->startOfDay();
        $interval = $localFrom->diffInDays($localTo) > 62 ? 'week' : 'day';
        $bucketStart = $interval === 'week' ? $localFrom->copy()->startOfWeek() : $localFrom->copy();

        $points = [];
        for ($d = $bucketStart->copy(); $d->lte($localTo); $interval === 'week' ? $d->addWeek() : $d->addDay()) {
            $points[$d->toDateString()] = ['date' => $d->toDateString(), 'leads' => 0, 'showings' => 0, 'offers' => 0, 'closings' => 0];
        }

        $range = [$from->copy()->utc(), $to->copy()->utc()];
        foreach ([
            'leads' => ['leads', 'created_at', fn ($q) => $q->whereNull('deleted_at')],
            'showings' => ['appointments', 'starts_at', fn ($q) => $q],
            'offers' => ['offers', 'submitted_at', fn ($q) => $q->whereNull('parent_id')],
            'closings' => ['transactions', 'closed_at', fn ($q) => $q->where('status', 'closed')],
        ] as $key => [$table, $column, $scope]) {
            $rows = $scope(DB::table($table))->whereBetween($column, $range)
                ->selectRaw("to_char(date_trunc(?, {$column} at time zone ?), 'YYYY-MM-DD') as bucket, count(*) as total", [$interval, $zone])
                ->groupBy('bucket')->pluck('total', 'bucket');
            foreach ($rows as $bucket => $total) {
                if (isset($points[$bucket])) {
                    $points[$bucket][$key] = (int) $total;
                }
            }
        }

        return ['interval' => $interval, 'points' => array_values($points)];
    }

    private function round(mixed $value, int $precision): ?float
    {
        return $value === null ? null : round((float) $value, $precision);
    }
}
