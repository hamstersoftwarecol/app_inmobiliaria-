<?php

namespace Tests\Feature\Listings;

use App\Application\Mls\MLSSyncDispatcher;
use App\Domain\Identity\Enums\RoleName;
use App\Models\MlsListing;
use App\Models\MlsSyncJob;
use App\Models\Property;
use App\Models\PropertyMedia;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MlsSyncPipelineTest extends TestCase
{
    use MlsTestHelpers;

    private const BASE = 'https://reso.example.test/odata';

    private function runSync(string $type, $connection): MlsSyncJob
    {
        // QUEUE_CONNECTION=sync in tests: the job runs inline.
        return app(MLSSyncDispatcher::class)->dispatch($connection, $type)->fresh();
    }

    public function test_incremental_sync_materializes_and_publishes_only_displayable_listings(): void
    {
        $connection = $this->authorizedConnection();

        $job = $this->runSync('incremental', $connection);

        $this->assertSame('succeeded', $job->status, (string) $job->error);
        $this->assertSame(3, $job->stats['fetched']);
        $this->assertSame(3, $job->stats['created']);
        $this->assertSame(3, MlsListing::count());

        $active = Property::whereHas('mlsListing', fn ($q) => $q->where('listing_key', 'TEST-KEY-0001'))->firstOrFail();
        $this->assertTrue($active->is_published);
        $this->assertSame('single_family', $active->propertyType->code);
        $this->assertSame(689000.0, (float) $active->list_price);

        // Withdrawn + "internet display = N" record is stored but not published.
        $withdrawn = Property::whereHas('mlsListing', fn ($q) => $q->where('listing_key', 'TEST-KEY-0002'))->firstOrFail();
        $this->assertFalse($withdrawn->is_published);

        // Photos: private photo kept but not displayable; non-HTTPS one dropped by normalization.
        $this->assertSame(2, PropertyMedia::where('property_id', $active->id)->count());
        $this->assertSame(1, PropertyMedia::where('property_id', $active->id)->where('display_allowed', true)->count());

        $this->assertSame('2026-03-02T08:30:00Z|TEST-KEY-0003', $connection->fresh()->last_cursor);
        $this->assertNotNull($connection->fresh()->last_synced_at);
        $this->assertSame(1, $active->statusHistory()->count());
    }

    public function test_resync_is_idempotent(): void
    {
        $connection = $this->authorizedConnection();
        $this->runSync('import', $connection);
        $firstUpdate = Property::max('updated_at');

        $job = $this->runSync('import', $connection);

        $this->assertSame(3, $job->stats['unchanged']);
        $this->assertSame(0, $job->stats['created'] + $job->stats['updated']);
        $this->assertSame(3, Property::count());
        $this->assertEquals($firstUpdate, Property::max('updated_at'));
    }

    public function test_sandbox_connections_never_publish(): void
    {
        $connection = $this->authorizedConnection(environment: 'sandbox');
        $this->runSync('incremental', $connection);

        $this->assertSame(3, Property::count());
        $this->assertSame(0, Property::where('is_published', true)->count());
    }

    public function test_display_rules_deny_by_default(): void
    {
        $connection = $this->authorizedConnection(rules: ['allow_public_display' => false]);
        $this->runSync('incremental', $connection);

        $this->assertSame(0, Property::where('is_published', true)->count());
    }

    public function test_sync_is_refused_without_licence_gate(): void
    {
        $connection = $this->authorizedConnection();
        config(['hitlease.mls.sync_enabled' => false]);

        $this->actingAsRole(RoleName::SuperAdmin);
        $this->postJson('/api/v1/admin/mls/sync', ['connection_id' => $connection->id, 'type' => 'incremental'])
            ->assertStatus(409)->assertJsonPath('error.code', 'mls_sync_disabled');
        $this->assertSame(0, MlsSyncJob::count());
    }

    public function test_failed_sync_keeps_existing_data_and_cursor(): void
    {
        $records = json_decode(file_get_contents(base_path('tests/Fixtures/mls/property.json')), true)['value'];
        $connection = $this->authorizedConnection('reso_web_api');
        config(['mls.http.retries' => 1]);

        Http::fakeSequence(self::BASE.'/*')
            ->push(['value' => [$records[0]]])              // changes page
            ->push(['value' => []])                         // media
            ->push(['error' => 'down'], 503);               // next run fails
        $first = $this->runSync('incremental', $connection);
        $this->assertSame('succeeded', $first->status, (string) $first->error);
        $cursor = $connection->fresh()->last_cursor;
        $this->assertTrue(Property::firstOrFail()->is_published);

        $second = $this->runSync('incremental', $connection);

        $this->assertSame('failed', $second->status);
        $this->assertStringContainsString('HTTP 503', $second->error);
        $this->assertSame($cursor, $connection->fresh()->last_cursor);
        $this->assertSame('error', $connection->fresh()->status);
        $this->assertTrue(Property::firstOrFail()->is_published, 'valid data untouched');
        $this->assertTrue($second->logs()->where('level', 'error')->exists());
    }

    public function test_bad_records_are_isolated_and_reported(): void
    {
        $records = json_decode(file_get_contents(base_path('tests/Fixtures/mls/property.json')), true)['value'];
        $broken = $records[0];
        $broken['ListingKey'] = str_repeat('X', 150); // exceeds column length
        $connection = $this->authorizedConnection('reso_web_api');

        Http::fake([self::BASE.'/Property*' => Http::response(['value' => [$broken, $records[2]]]), self::BASE.'/Media*' => Http::response(['value' => []])]);
        $job = $this->runSync('incremental', $connection);

        $this->assertSame('partial', $job->status);
        $this->assertSame(1, $job->stats['errors']);
        $this->assertSame(1, $job->stats['created']);
        $this->assertTrue($job->logs()->where('level', 'error')->where('listing_key', str_repeat('X', 100))->exists());
    }

    public function test_out_of_order_payloads_do_not_overwrite_newer_data(): void
    {
        $records = json_decode(file_get_contents(base_path('tests/Fixtures/mls/property.json')), true)['value'];
        $newer = $records[0];
        $older = array_merge($records[0], ['ListPrice' => 1, 'ModificationTimestamp' => '2026-01-01T00:00:00Z']);
        $connection = $this->authorizedConnection('reso_web_api');

        Http::fake([self::BASE.'/Media*' => Http::response(['value' => []]), self::BASE.'/Property*' => Http::sequence()->push(['value' => [$newer]])->push(['value' => [$older]])]);
        $this->runSync('incremental', $connection);
        MlsSyncJob::query()->update(['status' => 'succeeded']);
        $this->runSync('incremental', $connection);

        $this->assertSame(689000.0, (float) Property::firstOrFail()->list_price);
    }

    public function test_reconcile_hides_removed_listings_with_a_safety_threshold(): void
    {
        $connection = $this->authorizedConnection('reso_web_api');
        $records = json_decode(file_get_contents(base_path('tests/Fixtures/mls/property.json')), true)['value'];
        $extra = array_map(fn ($i) => array_merge($records[0], ['ListingKey' => "TEST-EXTRA-{$i}", 'ListingId' => "TEST-X{$i}"]), range(1, 9));
        $all = array_merge([$records[0]], $extra);
        config(['mls.sync.reconcile_max_removal_ratio' => 0.2]);

        Http::fake([
            self::BASE.'/Media*' => Http::response(['value' => []]),
            self::BASE.'/Property*' => Http::sequence()
                ->push(['value' => $all])                       // import (10 listings)
                ->push(['value' => array_slice($all, 1)])       // reconcile: 1 of 10 missing -> allowed
                ->push(['value' => []]),                        // reconcile: everything missing -> aborted
        ]);

        $this->runSync('import', $connection);
        $this->assertSame(10, Property::where('is_published', true)->count());

        $ok = $this->runSync('reconcile', $connection);
        $this->assertSame('succeeded', $ok->status, (string) $ok->error);
        $this->assertSame(1, $ok->stats['removed']);
        $this->assertNotNull(MlsListing::where('listing_key', 'TEST-KEY-0001')->value('removed_at'));
        $this->assertSame(9, Property::where('is_published', true)->count());

        $aborted = $this->runSync('reconcile', $connection);
        $this->assertSame('failed', $aborted->status);
        $this->assertStringContainsString('safety threshold', $aborted->error);
        $this->assertSame(9, Property::where('is_published', true)->count(), 'truncated feed must not wipe the inventory');
    }

    public function test_field_mappings_are_applied(): void
    {
        $connection = $this->authorizedConnection('reso_web_api');
        $connection->provider->fieldMappings()->create(['internal_field' => 'list_price', 'provider_field' => 'X_AskingPrice']);
        $records = json_decode(file_get_contents(base_path('tests/Fixtures/mls/property.json')), true)['value'];
        $record = array_merge($records[0], ['X_AskingPrice' => 701000]);

        Http::fake([self::BASE.'/Property*' => Http::response(['value' => [$record]]), self::BASE.'/Media*' => Http::response(['value' => []])]);
        $this->runSync('incremental', $connection);

        $this->assertSame(701000.0, (float) Property::firstOrFail()->list_price);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_dispatch_is_idempotent_while_a_job_is_pending(): void
    {
        $connection = $this->authorizedConnection();
        $pending = MlsSyncJob::create(['mls_connection_id' => $connection->id, 'type' => 'incremental', 'status' => 'queued']);

        $this->assertSame($pending->id, app(MLSSyncDispatcher::class)->dispatch($connection, 'incremental')->id);
        $this->assertSame(1, MlsSyncJob::count());
    }

    public function test_sync_due_command_queues_only_due_authorized_connections(): void
    {
        $due = $this->authorizedConnection();
        $fresh = $this->authorizedConnection();
        $fresh->forceFill(['last_synced_at' => now()])->save();
        $sandbox = $this->authorizedConnection(environment: 'sandbox');

        $this->artisan('hitlease:mls:sync-due')->expectsOutputToContain('Queued 1 incremental sync(s).')->assertSuccessful();

        $this->assertSame([$due->id], MlsSyncJob::pluck('mls_connection_id')->all());

        config(['hitlease.mls.sync_enabled' => false]);
        $this->artisan('hitlease:mls:sync-due')->expectsOutputToContain('disabled')->assertSuccessful();
        unset($sandbox);
    }

    public function test_monitoring_endpoints_and_permissions(): void
    {
        $connection = $this->authorizedConnection();

        $this->actingAsRole(RoleName::Broker);
        $this->getJson('/api/v1/admin/mls/status')->assertOk()->assertJsonPath('data.connections.0.is_stale', true);
        $this->postJson('/api/v1/admin/mls/sync', ['connection_id' => $connection->id, 'type' => 'incremental'])->assertForbidden();

        $this->actingAsRole(RoleName::SuperAdmin);
        $jobId = $this->postJson('/api/v1/admin/mls/sync', ['connection_id' => $connection->id, 'type' => 'incremental'])
            ->assertStatus(202)->json('data.id');

        $this->getJson("/api/v1/admin/mls/sync-jobs/{$jobId}")->assertOk()->assertJsonPath('data.status', 'succeeded');
        $this->getJson('/api/v1/admin/mls/sync-jobs')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/mls/status')
            ->assertOk()
            ->assertJsonPath('data.connections.0.is_stale', false)
            ->assertJsonPath('data.connections.0.listings.total', 3)
            ->assertJsonPath('data.connections.0.listings.published', 2);

        $this->putJson("/api/v1/admin/mls/providers/{$connection->mls_provider_id}/field-mappings", ['mappings' => [
            ['internal_field' => 'list_price', 'provider_field' => 'X_Price'],
        ]])->assertOk()->assertJsonPath('data.0.provider_field', 'X_Price');
        $this->putJson("/api/v1/admin/mls/providers/{$connection->mls_provider_id}/field-mappings", ['mappings' => [
            ['internal_field' => 'List Price;', 'provider_field' => 'x'],
        ]])->assertStatus(422);
    }
}
