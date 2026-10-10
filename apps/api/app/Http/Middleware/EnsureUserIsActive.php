<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Exceptions\AuthenticationFailed;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from deactivated accounts even if they still hold a
 * session cookie or token (defence in depth on top of revocation).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            } elseif ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
            }

            throw AuthenticationFailed::accountDeactivated();
        }

        return $next($request);
    }
}
