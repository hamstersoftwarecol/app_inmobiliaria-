<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Audit\AuditLogger;
use App\Application\Identity\SessionManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class PasswordController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionManager $sessions,
    ) {}

    /**
     * Always answers the same way to avoid account enumeration.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(['email' => Str::lower($request->string('email'))]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->audit->security('auth.password.reset_requested', User::where('email', Str::lower($request->string('email')))->first());
        }

        return response()->json([
            'message' => 'If an account exists for that e-mail, a password reset link has been sent.',
        ], 202);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            [
                'email' => Str::lower($request->string('email')),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
                // A reset must kick out every existing session and token.
                $this->sessions->revokeAll($user);
                event(new PasswordReset($user));
                $this->audit->security('auth.password.reset', $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }

    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->input('password')])->save();

        if ($request->boolean('logout_other_sessions', true)) {
            $token = $user->currentAccessToken();
            $this->sessions->revokeAll(
                $user,
                $request->hasSession() ? $request->session()->getId() : null,
                $token instanceof PersonalAccessToken ? $token->id : null,
            );
        }

        $this->audit->security('auth.password.changed', $user);

        return response()->json(['message' => 'Password updated.']);
    }
}
