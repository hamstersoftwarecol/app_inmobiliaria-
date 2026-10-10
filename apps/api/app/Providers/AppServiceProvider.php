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
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Credential endpoints: per-IP ceiling on top of the per-account lockout.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute((int) config('hitlease.security.auth_requests_per_minute', 10))->by('auth-ip:'.$request->ip()),
            Limit::perHour(100)->by('auth-ip-hour:'.$request->ip()),
        ]);

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
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
