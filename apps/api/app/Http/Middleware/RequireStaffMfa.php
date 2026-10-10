<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Exceptions\AuthorizationDenied;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff (super admin, broker, office manager) must have MFA enabled before
 * reaching administrative routes.
 */
class RequireStaffMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && config('hitlease.security.enforce_staff_mfa')
            && $user->isStaff()
            && ! $user->hasMfaEnabled()) {
            throw AuthorizationDenied::mfaEnrollmentRequired();
        }

        return $next($request);
    }
}
