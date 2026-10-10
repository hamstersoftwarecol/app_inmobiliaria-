<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardened defaults for a JSON-only API (OWASP Secure Headers).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // form-action and base-uri do not fall back to default-src.
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; form-action 'none'; base-uri 'none'");
        // Defence in depth if expose_php is left on (the Docker php.ini turns it off).
        $headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
