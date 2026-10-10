<?php

namespace Tests\Feature\Listings;

use App\Domain\Mls\Enums\ProviderStatus;
use App\Models\MlsConnection;
use App\Models\MlsProvider;

trait MlsTestHelpers
{
    /**
     * A production connection of an authorized provider using the given
     * driver, with permissive display rules unless overridden.
     */
    protected function authorizedConnection(string $driver = 'fixture', array $rules = [], string $environment = 'production'): MlsConnection
    {
        config(['app_inmobiliaria.mls.sync_enabled' => true]);

        $provider = MlsProvider::create(['code' => 'test_'.$driver.'_'.uniqid(), 'name' => 'Test MLS', 'driver' => $driver, 'protocol' => 'reso_web_api']);
        $provider->forceFill(['status' => ProviderStatus::Authorized, 'is_enabled' => true])->save();
        $provider->displayRule()->create(array_merge([
            'allow_public_display' => true,
            'allow_mobile_display' => true,
            'attribution_template' => 'Listing courtesy of {broker}',
            'disclaimer' => 'Synthetic test disclaimer.',
            'min_refresh_minutes' => 60,
        ], $rules));

        $connection = new MlsConnection([
            'name' => 'conn', 'environment' => $environment,
            'base_url' => 'https://reso.example.test/odata', 'token_url' => 'https://auth.example.test/token',
            'credentials' => ['access_token' => 'test-token'],
        ]);
        $connection->provider()->associate($provider)->save();

        return $connection->fresh();
    }
}
