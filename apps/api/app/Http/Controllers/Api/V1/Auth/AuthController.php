<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Audit\AuditLogger;
use App\Application\Identity\CredentialVerifier;
use App\Application\Identity\MfaService;
use App\Application\Identity\RegisterUser;
use App\Domain\Identity\Exceptions\AuthenticationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\MfaChallengeRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Two authentication channels sharing the same credential rules:
 *  - Web (first-party SPA): cookie session + CSRF (POST /auth/login).
 *  - Mobile: Sanctum personal access token (POST /auth/token).
 */
class AuthController extends Controller
{
    private const MFA_SESSION_KEY = 'auth.mfa_pending';

    public function __construct(
        private readonly CredentialVerifier $credentials,
        private readonly MfaService $mfa,
        private readonly AuditLogger $audit,
    ) {}

    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->validated());
        $payload = ['data' => (new UserResource($user))->withPermissions()];

        if ($request->filled('device_name')) {
            $payload['token'] = $this->issueToken($user, (string) $request->input('device_name'));
        } elseif ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return response()->json($payload, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['error' => [
                'code' => 'stateful_session_required',
                'message' => 'Cookie login is only available to first-party web clients. Mobile clients must use POST /api/v1/auth/token.',
                'details' => null,
                'request_id' => $request->attributes->get('request_id'),
            ]], 400);
        }

        $user = $this->credentials->verify($request->string('email'), $request->string('password'), (string) $request->ip());

        if ($user->hasMfaEnabled()) {
            $request->session()->put(self::MFA_SESSION_KEY, [
                'user_id' => $user->getKey(),
                'remember' => $request->boolean('remember'),
                'expires_at' => now()->addSeconds((int) config('hitlease.security.mfa_challenge_ttl'))->getTimestamp(),
            ]);

            return response()->json(['mfa_required' => true]);
        }

        return $this->completeSessionLogin($request, $user, $request->boolean('remember'));
    }

    public function mfaChallenge(MfaChallengeRequest $request): JsonResponse
    {
        $pending = $request->hasSession() ? $request->session()->get(self::MFA_SESSION_KEY) : null;

        if (! is_array($pending) || $pending['expires_at'] < now()->getTimestamp()) {
            $request->session()->forget(self::MFA_SESSION_KEY);

            throw AuthenticationFailed::mfaChallengeExpired();
        }

        $user = User::query()->findOrFail($pending['user_id']);
        $this->credentials->assertMfaNotLocked($user);

        if (! $this->mfa->verifyChallenge($user, $request->input('code'), $request->input('recovery_code'))) {
            $this->credentials->recordMfaFailure($user);

            throw AuthenticationFailed::invalidMfaCode();
        }
        $this->credentials->clearMfaFailures($user);

        $request->session()->forget(self::MFA_SESSION_KEY);

        return $this->completeSessionLogin($request, $user, (bool) $pending['remember'], mfa: true);
    }

    public function token(TokenRequest $request): JsonResponse
    {
        $user = $this->credentials->verify($request->string('email'), $request->string('password'), (string) $request->ip());

        if ($user->hasMfaEnabled()) {
            if (! $request->filled('mfa_code') && ! $request->filled('recovery_code')) {
                throw AuthenticationFailed::mfaRequired();
            }
            $this->credentials->assertMfaNotLocked($user);
            if (! $this->mfa->verifyChallenge($user, $request->input('mfa_code'), $request->input('recovery_code'))) {
                $this->credentials->recordMfaFailure($user);

                throw AuthenticationFailed::invalidMfaCode();
            }
            $this->credentials->clearMfaFailures($user);
        }

        $this->credentials->recordSuccessfulLogin($user, (string) $request->ip(), 'token');

        return response()->json([
            'token' => $this->issueToken($user, (string) $request->input('device_name')),
            'data' => (new UserResource($user->load('roles')))->withPermissions(),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => (new UserResource($request->user()->load('roles')))->withPermissions()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        $this->audit->security('auth.logout', $user);

        return response()->json(null, 204);
    }

    private function completeSessionLogin(Request $request, User $user, bool $remember, bool $mfa = false): JsonResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $this->credentials->recordSuccessfulLogin($user, (string) $request->ip(), $mfa ? 'web+mfa' : 'web');

        return response()->json([
            'mfa_required' => false,
            'data' => (new UserResource($user->load('roles')))->withPermissions(),
        ]);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_at: string|null}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes((int) $minutes) : null;
        $token = $user->createToken(mb_substr($deviceName, 0, 100), ['*'], $expiresAt);

        $this->audit->security('auth.token.issued', $user, ['device_name' => $deviceName]);

        return [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }
}
