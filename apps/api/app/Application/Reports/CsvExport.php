<?php

namespace App\Application\Reports;

use App\Domain\Identity\Enums\Permission;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports for agency reporting. Each dataset needs `reports.view` plus the permission that
 * already grants agency-wide access to that data, so an export never reveals more than the screens.
 * Cells that a spreadsheet would treat as formulas are neutralised (CSV injection).
 */
final class CsvExport
{
    public const DATASETS = ['leads', 'showings', 'offers', 'transactions', 'listings'];

    public static function permissionFor(string $dataset): Permission
    {
        return match ($dataset) {
            'leads' => Permission::LeadsViewAny,
            'showings' => Permission::AppointmentsViewAny,
            'offers' => Permission::OffersViewAny,
            'transactions' => Permission::TransactionsViewAny,
            'listings' => Permission::PropertiesViewAny,
        };
    }

    public function allowed(User $user, string $dataset): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission(Permission::ReportsView) && $user->hasPermission(self::permissionFor($dataset)));
    }

    public function download(string $dataset, Carbon $from, Carbon $to): StreamedResponse
    {
        [$headers, $query, $row] = $this->definition($dataset, [$from->copy()->utc(), $to->copy()->utc()]);
        $zone = (string) config('hitlease.agency_timezone');
        $name = sprintf('hitlease-%s-%s-to-%s.csv', $dataset, $from->copy()->setTimezone($zone)->toDateString(), $to->copy()->setTimezone($zone)->toDateString());

        return response()->streamDownload(function () use ($headers, $query, $row) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so spreadsheet apps detect the encoding
            fputcsv($out, $headers, escape: '');
            $query->chunk(500, function ($rows) use ($out, $row) {
                foreach ($rows as $r) {
                    fputcsv($out, array_map(self::safe(...), $row($r)), escape: '');
                }
            });
            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Prefixes values that start a formula in Excel/Sheets/LibreOffice. */
    public static function safe(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text) ? "'".$text : $text;
    }

    /**
     * @param  array{0: Carbon, 1: Carbon}  $range
     * @return array{0: list<string>, 1: Builder, 2: Closure(object): list<mixed>}
     */
    private function definition(string $dataset, array $range): array
    {
        $zone = (string) config('hitlease.agency_timezone');
        $local = fn (?string $ts) => $ts === null ? null : Carbon::parse($ts)->setTimezone($zone)->format('Y-m-d H:i');
        $agentName = fn (string $alias, string $column) => DB::raw("(select u.first_name || ' ' || u.last_name from agents a join users u on u.id = a.user_id where a.id = {$column}) as {$alias}");

        return match ($dataset) {
            'leads' => [
                ['id', 'created_at', 'status', 'source', 'intent', 'first_name', 'last_name', 'email', 'phone', 'assigned_agent', 'listing', 'first_contacted_at', 'contact_consent'],
                DB::table('leads')->whereNull('leads.deleted_at')->whereBetween('leads.created_at', $range)
                    ->leftJoin('properties', 'properties.id', '=', 'leads.property_id')
                    ->select('leads.*', 'properties.slug as listing', $agentName('agent_name', 'leads.assigned_agent_id'))
                    ->orderBy('leads.id'),
                fn ($r) => [$r->id, $local($r->created_at), $r->status, $r->source, $r->intent, $r->first_name, $r->last_name, $r->email, $r->phone, $r->agent_name, $r->listing, $local($r->first_contacted_at), $r->contact_consent ? 'yes' : 'no'],
            ],
            'showings' => [
                ['id', 'starts_at', 'status', 'listing', 'agent', 'visitor', 'lead_id', 'feedback_rating'],
                DB::table('appointments')->whereBetween('appointments.starts_at', $range)
                    ->leftJoin('properties', 'properties.id', '=', 'appointments.property_id')
                    ->select('appointments.*', 'properties.slug as listing', $agentName('agent_name', 'appointments.agent_id'))
                    ->orderBy('appointments.id'),
                fn ($r) => [$r->id, $local($r->starts_at), $r->status, $r->listing, $r->agent_name, $r->contact_name, $r->lead_id, $r->feedback_rating ?? null],
            ],
            'offers' => [
                ['id', 'parent_id', 'submitted_at', 'status', 'side', 'listing', 'amount', 'financing', 'buyer_agent', 'buyer_agent_external', 'closing_date'],
                DB::table('offers')->whereBetween('offers.submitted_at', $range)
                    ->join('properties', 'properties.id', '=', 'offers.property_id')
                    ->select('offers.*', 'properties.slug as listing', $agentName('agent_name', 'offers.buyer_agent_id'))
                    ->orderBy('offers.id'),
                fn ($r) => [$r->id, $r->parent_id, $local($r->submitted_at), $r->status, $r->side, $r->listing, $r->amount, $r->financing, $r->agent_name, $r->buyer_agent_external, $r->closing_date],
            ],
            'transactions' => [
                ['id', 'status', 'listing', 'sale_price', 'contract_date', 'closing_date', 'closed_at', 'cancelled_at', 'listing_agent', 'buyer_agent'],
                DB::table('transactions')
                    ->where(fn ($q) => $q->whereBetween('transactions.created_at', $range)->orWhereBetween('transactions.closed_at', $range)->orWhereBetween('transactions.cancelled_at', $range))
                    ->join('properties', 'properties.id', '=', 'transactions.property_id')
                    ->select('transactions.*', 'properties.slug as listing', $agentName('listing_agent_name', 'transactions.listing_agent_id'), $agentName('buyer_agent_name', 'transactions.buyer_agent_id'))
                    ->orderBy('transactions.id'),
                fn ($r) => [$r->id, $r->status, $r->listing, $r->sale_price, $r->contract_date, $r->closing_date, $local($r->closed_at), $local($r->cancelled_at), $r->listing_agent_name, $r->buyer_agent_name],
            ],
            'listings' => [
                ['id', 'slug', 'title', 'source', 'status', 'listing_type', 'is_published', 'list_price', 'city', 'postal_code', 'listing_agent', 'published_at', 'updated_at'],
                // Inventory snapshot (not limited to the period).
                DB::table('properties')->whereNull('properties.deleted_at')
                    ->select('properties.*', $agentName('agent_name', 'properties.listing_agent_id'))
                    ->orderBy('properties.id'),
                fn ($r) => [$r->id, $r->slug, $r->title, $r->source, $r->status, $r->listing_type, $r->is_published ? 'yes' : 'no', $r->list_price, $r->city, $r->postal_code, $r->agent_name, $local($r->published_at), $local($r->updated_at)],
            ],
        };
    }
}
