<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Audit\AuditLogger;
use App\Application\Identity\MfaService;
use App\Domain\Identity\Exceptions\AuthenticationFailed;
use App\Domain\Shared\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaConfirmRequest;
use App\Http\Requests\Auth\PasswordConfirmationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TOTP enrolment: setup (secret, not yet active) -> confirm (first valid code
 * activates MFA and returns one-time recovery codes) -> disable/regenerate.
 */
class MfaController extends Controller
{
    public function __construct(
        private readonly MfaService $mfa,
        private readonly AuditLogger $audit,
    ) {}

    public function setup(PasswordConfirmationRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasMfaEnabled()) {
            throw new DomainException('mfa_already_enabled', 'Multi-factor authentication is already enabled.', 409);
        }

        $secret = $this->mfa->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_enabled_at' => null])->save();
        $this->audit->security('auth.mfa.setup_started', $user);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $this->mfa->provisioningUri($user, $secret),
        ]);
    }

    public function confirm(MfaConfirmRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasMfaEnabled()) {
            throw new DomainException('mfa_already_enabled', 'Multi-factor authentication is already enabled.', 409);
        }
        if ($user->mfa_secret === null) {
            throw new DomainException('mfa_setup_required', 'Start the MFA setup first.', 409);
        }
        if (! $this->mfa->verifyCode($user, (string) $user->mfa_secret, $request->string('code'))) {
            throw AuthenticationFailed::invalidMfaCode();
        }

        $user->forceFill(['mfa_enabled_at' => now()])->save();
        $codes = $this->mfa->regenerateRecoveryCodes($user);
        $this->audit->security('auth.mfa.enabled', $user);

        return response()->json(['mfa_enabled' => true, 'recovery_codes' => $codes]);
    }

    public function regenerateRecoveryCodes(PasswordConfirmationRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->assertSecondFactor($request);

        $codes = $this->mfa->regenerateRecoveryCodes($user);
        $this->audit->security('auth.mfa.recovery_codes_regenerated', $user);

        return response()->json(['recovery_codes' => $codes]);
    }

    public function disable(PasswordConfirmationRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->assertSecondFactor($request);

        $this->mfa->disable($user);
        $this->audit->security('auth.mfa.disabled', $user);

        return response()->json(['mfa_enabled' => false]);
    }

    private function assertSecondFactor(Request $request): void
    {
        $user = $request->user();

        if (! $user->hasMfaEnabled()) {
            throw new DomainException('mfa_not_enabled', 'Multi-factor authentication is not enabled.', 409);
        }
        if (! $this->mfa->verifyChallenge($user, $request->input('code'), null)) {
            throw AuthenticationFailed::invalidMfaCode();
        }
    }
}
