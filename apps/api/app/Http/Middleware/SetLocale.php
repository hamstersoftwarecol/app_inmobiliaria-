<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selects English (default) or Spanish for validation and e-mail messages.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED) ?? 'en';
        app()->setLocale($locale);

        return $next($request);
    }
}
