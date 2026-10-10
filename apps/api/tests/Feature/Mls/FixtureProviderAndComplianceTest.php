<?php

namespace Tests\Feature\Mls;

use App\Application\Mls\MLSComplianceService;
use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Domain\Mls\Exceptions\MLSException;
use App\Infrastructure\Mls\Providers\FixtureMLSProvider;
use App\Models\MlsConnection;
use App\Models\MlsDisplayRule;
use App\Models\MlsProvider;
use Tests\TestCase;

class FixtureProviderAndComplianceTest extends TestCase
{
    private function fixtureProvider(): FixtureMLSProvider
    {
        $provider = MlsProvider::create(['code' => 'fixture', 'name' => 'Fixture', 'driver' => 'fixture', 'protocol' => 'other']);
        $connection = new MlsConnection(['name' => 'dev', 'environment' => 'sandbox']);
        $connection->provider()->associate($provider)->save();

        return app(MLSConnectionResolver::class)->resolve($connection);
    }

    public function test_fixture_provider_supports_the_full_contract(): void
    {
        $provider = $this->fixtureProvider();

        $this->assertCount(3, iterator_to_array($provider->fetchListings(), false));
        $this->assertCount(1, iterator_to_array($provider->fetchListings(['status' => ['Active']]), false));
        $this->assertSame('TEST-0001', $provider->fetchListing('TEST-KEY-0001')?->listingId);
        $this->assertNull($provider->fetchListing('missing'));
        $this->assertCount(2, iterator_to_array($provider->fetchMedia('TEST-KEY-0001'), false));

        $first = $provider->fetchChanges();
        $this->assertCount(3, $first->listings);
        $this->assertSame('2026-03-02T08:30:00Z|TEST-KEY-0003', $first->nextCursor);
        $this->assertCount(0, $provider->fetchChanges($first->nextCursor)->listings);
    }

    public function test_fixture_provider_is_refused_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->fixtureProvider();
            $this->fail('Fixture provider must not be available in production');
        } catch (MLSException $e) {
            $this->assertSame('mls_fixtures_not_allowed', $e->errorCode);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_compliance_rules_default_to_deny(): void
    {
        $provider = $this->fixtureProvider();
        $active = $provider->fetchListing('TEST-KEY-0001');
        $withdrawn = $provider->fetchListing('TEST-KEY-0002');
        $compliance = new MLSComplianceService;

        $this->assertFalse($compliance->canDisplay($active, null), 'no rules => no display');

        $rules = new MlsDisplayRule(['allow_public_display' => true, 'allow_mobile_display' => false, 'hide_withdrawn_immediately' => true]);
        $this->assertTrue($compliance->canDisplay($active, $rules));
        $this->assertFalse($compliance->canDisplay($active, $rules, MLSComplianceService::CHANNEL_MOBILE));
        $this->assertFalse($compliance->canDisplay($withdrawn, $rules), 'withdrawn/opted-out listings hidden');
        $this->assertFalse($compliance->canProcessWithAi($rules));

        $rules->photo_rules = ['max_photos' => 1];
        $media = iterator_to_array($provider->fetchMedia('TEST-KEY-0001'), false);
        $this->assertTrue($compliance->canDisplayMedia($media[0], $rules));
        $this->assertFalse($compliance->canDisplayMedia($media[1], $rules));

        $rules->allowed_fields = ['list_price'];
        $this->assertSame(['list_price' => 1], $compliance->filterFields(['list_price' => 1, 'remarks' => 'x'], $rules));

        $rules->attribution_template = 'Listing provided by {broker}';
        $this->assertSame('Listing provided by Test Realty Office', $compliance->attribution($active, $rules));
    }
}
