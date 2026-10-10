<?php

namespace App\Application\Crm;

use App\Domain\Crm\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Pipeline figures for leads created in a period (agency calendar days). */
final class CrmReports
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $base = fn () => Lead::query()->whereBetween('created_at', [$from, $to]);

        $total = $base()->count();
        $won = $base()->where('status', LeadStatus::Won->value)->count();
        $byStatus = $base()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $bySource = $base()->selectRaw('source, count(*) as total')->groupBy('source')->orderByDesc('total')->pluck('total', 'source');

        $response = $base()->whereNotNull('first_contacted_at')
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from (first_contacted_at - created_at)) / 60) as median_minutes')
            ->value('median_minutes');

        $byAgent = DB::table('leads')
            ->join('agents', 'agents.id', '=', 'leads.assigned_agent_id')
            ->join('users', 'users.id', '=', 'agents.user_id')
            ->whereNull('leads.deleted_at')
            ->whereBetween('leads.created_at', [$from, $to])
            ->groupBy('agents.id', 'users.first_name', 'users.last_name')
            ->orderByDesc('assigned')
            ->selectRaw("agents.id as agent_id, users.first_name || ' ' || users.last_name as full_name, count(*) as assigned,
                count(*) filter (where leads.status = 'won') as won,
                percentile_cont(0.5) within group (order by extract(epoch from (leads.first_contacted_at - leads.created_at)) / 60)
                    filter (where leads.first_contacted_at is not null) as median_response_minutes")
            ->get()
            ->map(fn ($r) => [
                'agent_id' => (int) $r->agent_id,
                'full_name' => $r->full_name,
                'assigned' => (int) $r->assigned,
                'won' => (int) $r->won,
                'median_response_minutes' => $r->median_response_minutes !== null ? round((float) $r->median_response_minutes, 1) : null,
            ]);

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'total' => $total,
            'won' => $won,
            'conversion_rate' => $total > 0 ? round($won / $total, 4) : null,
            'unassigned_open' => Lead::query()->whereNull('assigned_agent_id')->whereIn('status', LeadStatus::openValues())->count(),
            'median_first_response_minutes' => $response !== null ? round((float) $response, 1) : null,
            'by_status' => collect(LeadStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)]),
            'by_source' => $bySource->map(fn ($v) => (int) $v),
            'by_agent' => $byAgent,
        ];
    }
}
