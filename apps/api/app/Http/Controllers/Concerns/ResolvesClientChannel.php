<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Mobile clients send `X-Hitlease-Client: mobile` so MLS rules that forbid
 * mobile display are honoured; everything else is the web channel.
 */
trait ResolvesClientChannel
{
    protected function channel(Request $request): string
    {
        return strtolower((string) $request->header('X-Hitlease-Client')) === 'mobile' ? 'mobile' : 'web';
    }
}
