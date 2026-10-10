<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Signed link (validated by the "signed" middleware) + e-mail hash check.
     */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            $this->audit->security('auth.email.verified', $user);
        }

        return response()->json(['message' => 'E-mail address verified.', 'verified' => true]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'E-mail address already verified.', 'verified' => true]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.', 'verified' => false], 202);
    }
}
