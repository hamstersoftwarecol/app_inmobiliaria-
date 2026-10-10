<?php

namespace App\Providers;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Mls\Contracts\MLSConnectionResolver;
use App\Infrastructure\Mls\MLSConnectionManager;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MLSConnectionResolver::class, MLSConnectionManager::class);
    }

    public function boot(): void
    {
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configurePasswordPolicy();
    }

    private function configureAuthorization(): void
    {
        // Super admins pass every gate/policy check (actions remain audited).
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // One gate per permission, e.g. Gate::allows('users.view_any') / ->can('users.view_any').
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: self::clientKey($request)));

        // Credential endpoints: per-IP ceiling on top of the per-account lockout.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute((int) config('hitlease.security.auth_requests_per_minute', 10))->by('auth-ip:'.$request->ip()),
            Limit::perHour(100)->by('auth-ip-hour:'.$request->ip()),
        ]);

        // Public contact forms: per visitor (see clientKey), generous enough for real people.
        RateLimiter::for('inquiries', fn (Request $request) => [
            Limit::perMinute(5)->by('inquiry:'.self::clientKey($request)),
            Limit::perHour(20)->by('inquiry-hour:'.self::clientKey($request)),
        ]);

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by('search:'.($request->user()?->id ?: self::clientKey($request))));
    }

    /**
     * Rate-limit key for anonymous traffic. Server-side rendering (Next.js) calls the API from one
     * address for every visitor, so it presents the shared `API_SSR_KEY` and the visitor's IP; the
     * forwarded IP is only trusted when the key matches, otherwise the connection IP is used.
     */
    public static function clientKey(Request $request): string
    {
        $key = (string) config('hitlease.security.ssr_key');
        $presented = (string) $request->header('X-Hitlease-SSR-Key');
        $forwarded = (string) $request->header('X-Hitlease-Client-IP');

        if ($key !== '' && $presented !== '' && hash_equals($key, $presented)) {
            return 'ssr:'.(filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : 'unknown');
        }

        return (string) $request->ip();
    }

    private function configurePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12)->max(128)->letters()->mixedCase()->numbers()->symbols();

            // Breached-password check (HIBP k-anonymity API) only where outbound access is expected.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
