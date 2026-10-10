<?php

namespace Tests\Feature\Mls;

use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Domain\Mls\Enums\ProviderStatus;
use App\Domain\Mls\Exceptions\MLSException;
use App\Infrastructure\Mls\Providers\RESOWebAPIProvider;
use App\Models\MlsConnection;
use App\Models\MlsProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the generic RESO Web API adapter against faked HTTP responses.
 * The hosts below are reserved test domains; no real MLS is contacted.
 */
class RESOWebAPIProviderTest extends TestCase
{
    private const BASE = 'https://reso.example.test/odata';

    private const TOKEN_URL = 'https://auth.example.test/oauth2/token';

    private function connection(string $environment = 'sandbox', array $credentials = []): MlsConnection
    {
        $provider = MlsProvider::create(['code' => 'reso_test', 'name' => 'RESO Test', 'driver' => 'reso_web_api', 'protocol' => 'reso_web_api']);
        $provider->displayRule()->create([]);

        $connection = new MlsConnection([
            'name' => 'test',
            'environment' => $environment,
            'base_url' => self::BASE,
            'token_url' => self::TOKEN_URL,
            'credentials' => $credentials ?: ['client_id' => 'id', 'client_secret' => 'secret', 'scope' => 'api'],
        ]);
        $connection->provider()->associate($provider);
        $connection->save();

        return $connection;
    }

    private function fixtureRecords(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/mls/property.json')), true)['value'];
    }

    private function fakeToken(): array
    {
        return [self::TOKEN_URL => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600])];
    }

    public function test_authenticates_with_client_credentials(): void
    {
        Http::fake($this->fakeToken());
        $connection = $this->connection();

        app(MLSConnectionResolver::class)->resolve($connection)->authenticate();

        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL
            && $r['grant_type'] === 'client_credentials' && $r['client_id'] === 'id' && $r['scope'] === 'api');
        $this->assertNotNull($connection->fresh()->last_authenticated_at);
    }

    public function test_failed_authentication_raises_domain_error(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_client'], 401)]);

        $this->expectException(MLSException::class);
        app(MLSConnectionResolver::class)->resolve($this->connection())->authenticate();
    }

    public function test_fetch_listings_follows_server_paging_and_normalizes(): void
    {
        [$first, $second, $third] = $this->fixtureRecords();
        Http::fake($this->fakeToken() + [
            self::BASE.'/Property*' => fn (Request $r) => str_contains($r->url(), 'skiptoken=abc')
                ? Http::response(['value' => [$third, ['NoKey' => true]]])
                : Http::response(['value' => [$first, $second], '@odata.nextLink' => self::BASE.'/Property?$skiptoken=abc']),
        ]);

        $listings = iterator_to_array(app(MLSConnectionResolver::class)->resolve($this->connection())->fetchListings(['status' => ['Active']]), false);

        $this->assertSame(['TEST-KEY-0001', 'TEST-KEY-0002', 'TEST-KEY-0003'], array_map(fn ($l) => $l->listingKey, $listings));
        $this->assertSame('reso_test', $listings[0]->sourceMls);
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), "\$filter=(StandardStatus eq 'Active')")
            && $r->hasHeader('Authorization', 'Bearer tok-123'));
    }

    public function test_paging_links_to_foreign_hosts_are_refused(): void
    {
        Http::fake($this->fakeToken() + [
            self::BASE.'/Property*' => Http::response(['value' => [], '@odata.nextLink' => 'https://evil.example.test/steal']),
        ]);

        $this->expectException(MLSException::class);
        iterator_to_array(app(MLSConnectionResolver::class)->resolve($this->connection())->fetchListings());
    }

    public function test_fetch_changes_uses_a_stable_composite_cursor(): void
    {
        Http::fake($this->fakeToken() + [
            self::BASE.'/Property*' => Http::response(['value' => array_slice($this->fixtureRecords(), 0, 2)]),
        ]);

        $batch = app(MLSConnectionResolver::class)->resolve($this->connection())->fetchChanges('2026-03-01T09:00:00Z|TEST-KEY-0000');

        $this->assertCount(2, $batch->listings);
        $this->assertSame('2026-03-01T10:00:00Z|TEST-KEY-0002', $batch->nextCursor);
        $this->assertFalse($batch->hasMore);
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()),
            "(ModificationTimestamp gt 2026-03-01T09:00:00.000Z or (ModificationTimestamp eq 2026-03-01T09:00:00.000Z and ListingKey gt 'TEST-KEY-0000'))"));
    }

    public function test_static_bearer_token_and_missing_listing(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['error' => 'not found'], 404)]);

        $provider = app(MLSConnectionResolver::class)->resolve($this->connection(credentials: ['access_token' => 'static-token']));

        $this->assertNull($provider->fetchListing("O'Brien-1"));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer static-token')
            && str_contains(urldecode($r->url()), "Property('O''Brien-1')"));
    }

    public function test_media_is_filtered_by_record_key(): void
    {
        $media = json_decode(file_get_contents(base_path('tests/Fixtures/mls/media.json')), true);
        Http::fake($this->fakeToken() + [self::BASE.'/Media*' => Http::response($media)]);

        $items = iterator_to_array(app(MLSConnectionResolver::class)->resolve($this->connection())->fetchMedia('TEST-KEY-0001'), false);

        $this->assertSame(['M-1', 'M-3'], array_map(fn ($m) => $m->mediaKey, $items), 'non-HTTPS media dropped');
    }

    public function test_client_side_rate_limit(): void
    {
        config(['mls.http.max_requests_per_minute' => 1]);
        Http::fake([self::BASE.'/*' => Http::response(['value' => []])]);
        $provider = app(MLSConnectionResolver::class)->resolve($this->connection(credentials: ['access_token' => 't']));

        $provider->fetchChanges();

        try {
            $provider->fetchChanges();
            $this->fail('Expected rate limit');
        } catch (MLSException $e) {
            $this->assertSame('mls_rate_limited', $e->errorCode);
        }
    }

    public function test_production_connections_are_gated_by_licence(): void
    {
        $connection = $this->connection('production');
        $resolver = app(MLSConnectionResolver::class);

        config(['app_inmobiliaria.mls.sync_enabled' => false]);
        try {
            $resolver->resolve($connection);
            $this->fail('Expected sync disabled');
        } catch (MLSException $e) {
            $this->assertSame('mls_sync_disabled', $e->errorCode);
        }

        config(['app_inmobiliaria.mls.sync_enabled' => true]);
        try {
            $resolver->resolve($connection);
            $this->fail('Expected not authorized');
        } catch (MLSException $e) {
            $this->assertSame('mls_not_authorized', $e->errorCode);
        }

        $connection->provider->forceFill(['status' => ProviderStatus::Authorized, 'is_enabled' => true])->save();
        $this->assertInstanceOf(RESOWebAPIProvider::class, $resolver->resolve($connection->fresh()));
    }

    public function test_insecure_base_urls_are_refused(): void
    {
        $connection = $this->connection(credentials: ['access_token' => 't']);
        $connection->forceFill(['base_url' => 'http://reso.example.test/odata'])->save();

        $this->expectException(MLSException::class);
        app(MLSConnectionResolver::class)->resolve($connection)->fetchChanges();
    }
}
