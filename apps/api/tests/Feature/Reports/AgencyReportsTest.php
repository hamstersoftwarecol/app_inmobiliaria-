<?php

namespace Tests\Feature\Reports;

use App\Application\Reports\CsvExport;
use App\Domain\Identity\Enums\RoleName;
use App\Models\Agent;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgencyReportsTest extends TestCase
{
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday June 16, 2027, 10:00 in New Jersey.
        $this->travelTo(Carbon::parse('2027-06-16 14:00:00', 'UTC'));
        $this->agent = Agent::factory()->create();
    }

    private function seedActivity(): void
    {
        $listing = Property::factory()->forAgent($this->agent)->published()->create(['list_price' => 500000, 'published_at' => '2027-06-10 15:00:00+00']);
        Property::factory()->forAgent($this->agent)->published()->create(['list_price' => 700000, 'published_at' => '2027-01-10 15:00:00+00']);
        Property::factory()->forAgent($this->agent)->create(); // draft: not counted

        Lead::factory()->create(['created_at' => '2027-06-14 16:00:00+00', 'status' => 'won', 'first_name' => '=HYPERLINK("http://evil.example")']);
        Lead::factory()->create(['created_at' => '2027-06-15 03:30:00+00']); // June 14 23:30 in New Jersey
        Lead::factory()->create(['created_at' => '2027-03-01 15:00:00+00']); // outside the period

        Appointment::query()->forceCreate(['agent_id' => $this->agent->id, 'property_id' => $listing->id, 'contact_name' => 'V', 'starts_at' => '2027-06-12 15:00:00+00', 'ends_at' => '2027-06-12 15:30:00+00', 'timezone' => 'America/New_York', 'status' => 'completed']);
        Appointment::query()->forceCreate(['agent_id' => $this->agent->id, 'property_id' => $listing->id, 'contact_name' => 'W', 'starts_at' => '2027-06-13 15:00:00+00', 'ends_at' => '2027-06-13 15:30:00+00', 'timezone' => 'America/New_York', 'status' => 'no_show']);

        $offer = Offer::query()->create(['property_id' => $listing->id, 'buyer_name' => 'B', 'amount' => 480000, 'financing' => 'cash', 'side' => 'buyer']);
        $offer->forceFill(['status' => 'countered', 'submitted_at' => '2027-06-11 15:00:00+00'])->save();
        $counter = Offer::query()->create(['property_id' => $listing->id, 'parent_id' => $offer->id, 'buyer_name' => 'B', 'amount' => 490000, 'financing' => 'cash', 'side' => 'seller']);
        $counter->forceFill(['status' => 'closed', 'submitted_at' => '2027-06-12 15:00:00+00'])->save();
        $lost = Offer::query()->create(['property_id' => $listing->id, 'buyer_name' => 'C', 'amount' => 450000, 'financing' => 'fha', 'side' => 'buyer']);
        $lost->forceFill(['status' => 'rejected', 'submitted_at' => '2027-06-11 16:00:00+00'])->save();

        Transaction::query()->create(['property_id' => $listing->id, 'offer_id' => $counter->id, 'listing_agent_id' => $this->agent->id, 'sale_price' => 490000, 'contract_date' => '2027-06-12'])
            ->forceFill(['status' => 'closed', 'closed_at' => '2027-06-15 18:00:00+00'])->save();
    }

    public function test_the_dashboard_summarises_the_period_in_agency_days(): void
    {
        $this->seedActivity();
        $this->actingAsRole(RoleName::Broker);

        $data = $this->getJson('/api/v1/admin/dashboard?from=2027-06-10&to=2027-06-16')->assertOk()->json('data');

        $this->assertSame(['2027-06-10', '2027-06-16', 'America/New_York'], [$data['from'], $data['to'], $data['timezone']]);
        $this->assertSame(2, $data['listings']['published']);
        $this->assertSame(1, $data['listings']['new_in_period']);
        $this->assertEquals(600000, $data['listings']['median_active_price']);
        $this->assertSame(['created' => 2, 'won' => 1, 'conversion_rate' => 0.5], array_intersect_key($data['leads'], array_flip(['created', 'won', 'conversion_rate'])));
        $this->assertSame(['scheduled' => 2, 'completed' => 1, 'no_show' => 1, 'cancelled_or_declined' => 0], $data['showings']);
        // Negotiations (original offers): one ended in a deal through its counter, one was rejected.
        $this->assertSame(['submitted' => 2, 'accepted' => 1, 'acceptance_rate' => 0.5], $data['offers']);
        $this->assertSame(1, $data['transactions']['closed']);
        $this->assertEquals(490000, $data['transactions']['closed_volume']);

        $series = $data['series'];
        $this->assertSame('day', $series['interval']);
        $this->assertCount(7, $series['points']);
        $byDate = collect($series['points'])->keyBy('date');
        $this->assertSame(2, $byDate['2027-06-14']['leads']); // the 23:30 lead counts on June 14 locally
        $this->assertSame(1, $byDate['2027-06-15']['closings']);
        $this->assertSame(0, $byDate['2027-06-16']['leads']);
    }

    public function test_long_periods_use_weekly_buckets_and_are_capped(): void
    {
        $this->actingAsRole(RoleName::Broker);
        $series = $this->getJson('/api/v1/admin/dashboard?from=2027-03-01&to=2027-06-16')->assertOk()->json('data.series');
        $this->assertSame('week', $series['interval']);
        $this->assertSame('2027-03-01', $series['points'][0]['date']); // a Monday

        $this->getJson('/api/v1/admin/dashboard?from=2025-01-01&to=2027-06-16')->assertStatus(422)->assertJsonPath('error.code', 'range_too_large');
        $this->getJson('/api/v1/admin/dashboard?from=2027-06-16&to=2027-06-01')->assertStatus(422);
    }

    public function test_access_follows_permissions(): void
    {
        Sanctum::actingAs(User::find($this->agent->user_id)->fresh());
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->get('/api/v1/admin/exports/leads', ['Accept' => 'application/json'])->assertForbidden();

        $this->actingAsRole(RoleName::OfficeManager);
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->get('/api/v1/admin/exports/leads', ['Accept' => 'application/json'])->assertOk();
        $this->get('/api/v1/admin/exports/secrets', ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_csv_exports_are_audited_and_neutralise_formulas(): void
    {
        $this->seedActivity();
        $broker = $this->actingAsRole(RoleName::Broker);

        $response = $this->get('/api/v1/admin/exports/leads?from=2027-06-10&to=2027-06-16')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('hitlease-leads-2027-06-10-to-2027-06-16.csv', $response->headers->get('Content-Disposition'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFid,created_at,status", $csv);
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertCount(3, $lines); // header + 2 leads in the period
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('2027-06-14 23:30', $csv); // agency local time

        foreach (['showings' => 3, 'offers' => 4, 'transactions' => 2, 'listings' => 4] as $dataset => $rows) {
            $content = $this->get("/api/v1/admin/exports/{$dataset}?from=2027-06-10&to=2027-06-16")->assertOk()->streamedContent();
            $this->assertCount($rows, array_filter(explode("\n", trim(substr($content, 3)))), $dataset);
        }

        $this->assertSame(5, AuditLog::query()->where('event', 'reports.exported')->where('user_id', $broker->id)->count());
        $this->assertSame('leads', AuditLog::query()->where('event', 'reports.exported')->orderBy('id')->first()->metadata['dataset']);
    }

    public function test_formula_guard_keeps_plain_numbers(): void
    {
        $this->assertSame('-120.50', CsvExport::safe('-120.50'));
        $this->assertSame("'-cmd", CsvExport::safe('-cmd'));
        $this->assertSame("'@SUM(A1)", CsvExport::safe('@SUM(A1)'));
        $this->assertSame('', CsvExport::safe(null));
    }
}
