<?php

namespace App\Infrastructure\Mls;

use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Domain\Mls\Contracts\MLSProviderInterface;
use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsConnection;
use Illuminate\Contracts\Container\Container;

/**
 * Builds MLS adapters from stored connections and enforces the licence gate:
 * production connections require the global switch (MLS_SYNC_ENABLED) AND a
 * provider explicitly marked authorized + enabled by a super admin.
 * Sandbox connections can be built for connectivity tests.
 */
class MLSConnectionManager implements MLSConnectionResolver
{
    public function __construct(private readonly Container $container) {}

    public function resolve(MlsConnection $connection): MLSProviderInterface
    {
        $connection->loadMissing('provider.displayRule');
        $provider = $connection->provider;
        $driver = (string) $provider?->driver;

        $class = config("mls.drivers.{$driver}");
        if (! is_string($class) || ! is_subclass_of($class, MLSProviderInterface::class)) {
            throw MLSException::unknownDriver($driver);
        }

        if ($connection->isProduction()) {
            if (! config('app_inmobiliaria.mls.sync_enabled')) {
                throw MLSException::syncDisabled();
            }
            if (! $provider->isAuthorizedForProduction()) {
                throw MLSException::notAuthorized($provider->code);
            }
        }

        return $this->container->make($class, ['connection' => $connection]);
    }

    public function drivers(): array
    {
        return array_keys((array) config('mls.drivers', []));
    }
}
