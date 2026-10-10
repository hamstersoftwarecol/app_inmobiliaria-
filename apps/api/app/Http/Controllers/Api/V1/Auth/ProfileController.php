<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Audit\AuditLogger;
use App\Application\Identity\MfaService;
use App\Application\Identity\SessionManager;
use App\Domain\Audit\AuditCategory;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Exceptions\AuthenticationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordConfirmationRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->safe()->except('current_password'));
        $changes = $user->getDirty();
        $original = array_intersect_key($user->getOriginal(), $changes);

        if (array_key_exists('email', $changes)) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($changes !== []) {
            $this->audit->log(AuditCategory::Security, 'auth.profile.updated', $user, $user, $original, $changes);
        }
        if (array_key_exists('email', $changes)) {
            $user->sendEmailVerificationNotification();
        }

        return (new UserResource($user->load('roles')))->withPermissions();
    }

    /**
     * Self-service deactivation. Requires the password (and MFA code when enabled).
     */
    public function deactivate(PasswordConfirmationRequest $request, SessionManager $sessions, MfaService $mfa): JsonResponse
    {
        $user = $request->user();

        if ($user->hasMfaEnabled() && ! $mfa->verifyChallenge($user, $request->input('code'), null)) {
            throw AuthenticationFailed::invalidMfaCode();
        }

        $user->forceFill(['status' => UserStatus::Deactivated, 'deactivated_at' => now()])->save();
        $sessions->revokeAll($user);
        $this->audit->security('auth.account.deactivated', $user);

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
        }

        return response()->json(null, 204);
    }
}
