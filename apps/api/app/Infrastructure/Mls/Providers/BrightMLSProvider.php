<?php

namespace App\Infrastructure\Mls\Providers;

/**
 * Adapter placeholder for Bright MLS.
 *
 * STATUS: blocked by external authorization. The agency has not yet supplied
 * a signed data licence, IDX/VOW rules or the official technical
 * specification for this provider. Until then this adapter assumes the RESO
 * Web API Core behaviour of its parent and MUST NOT be enabled in production
 * (MLSConnectionManager enforces this). Once the specification is received:
 *  - confirm protocol/authentication (replace the parent if it is not RESO Web API),
 *  - fill fieldMap() with provider-specific field names,
 *  - set SPECIFICATION_VERIFIED = true after contract tests pass.
 *
 * See docs/mls/integration-plan.md.
 */
class BrightMLSProvider extends RESOWebAPIProvider
{
    protected const CODE = 'bright';

    protected const SPECIFICATION_VERIFIED = false;

    protected function fieldMap(): array
    {
        return [];
    }
}
