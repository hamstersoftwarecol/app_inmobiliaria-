<?php

namespace App\Domain\Mls\Exceptions;

use App\Domain\Shared\DomainException;

final class MLSException extends DomainException
{
    public static function unknownDriver(string $driver): self
    {
        return new self('mls_unknown_driver', "No MLS adapter is registered for driver [{$driver}].", 422);
    }

    public static function notAuthorized(string $provider): self
    {
        return new self('mls_not_authorized', "MLS provider [{$provider}] is not authorized for production use. A signed data licence and official specification are required.", 409);
    }

    public static function syncDisabled(): self
    {
        return new self('mls_sync_disabled', 'MLS synchronization is globally disabled (MLS_SYNC_ENABLED=false).', 409);
    }

    public static function missingConfiguration(string $what): self
    {
        return new self('mls_missing_configuration', "MLS connection is missing required configuration: {$what}.", 422);
    }

    public static function authenticationFailed(string $provider, int $status): self
    {
        return new self('mls_authentication_failed', "Authentication with MLS provider [{$provider}] failed (HTTP {$status}).", 502);
    }

    public static function requestFailed(string $provider, int $status): self
    {
        return new self('mls_request_failed', "Request to MLS provider [{$provider}] failed (HTTP {$status}).", 502);
    }

    public static function fixturesNotAllowed(): self
    {
        return new self('mls_fixtures_not_allowed', 'Fixture MLS data can only be used in local and testing environments.', 409);
    }

    public static function rateLimited(string $provider, int $retryAfter): self
    {
        return new self('mls_rate_limited', "Client-side request budget for MLS provider [{$provider}] exhausted.", 429, ['retry_after' => $retryAfter]);
    }

    public static function insecureUrl(string $url): self
    {
        return new self('mls_insecure_url', 'MLS endpoints must use HTTPS and stay on the configured host.', 422, ['url' => $url]);
    }
}
