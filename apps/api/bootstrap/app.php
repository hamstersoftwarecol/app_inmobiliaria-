<?php

use App\Domain\Shared\DomainException;
use App\Http\ApiErrorRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireStaffMfa;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cookie-based (SPA) authentication for first-party stateful domains.
        $middleware->statefulApi();
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [SetLocale::class]);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'mfa.staff' => RequireStaffMfa::class,
        ]);

        // The API never redirects unauthenticated users to a login page.
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(new ApiErrorRenderer);
        $exceptions->dontReport([
            DomainException::class,
        ]);
    })->create();
