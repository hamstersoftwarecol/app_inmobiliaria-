<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Audit\AuditLogger;
use App\Application\Identity\SessionManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionManager $sessions,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$sessionId, $tokenId] = $this->current($request);

        return response()->json(['data' => $this->sessions->list($request->user(), $sessionId, $tokenId)]);
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        if (! $this->sessions->revoke($request->user(), $session)) {
            abort(404);
        }

        $this->audit->security('auth.session.revoked', $request->user(), ['session' => $session]);

        return response()->json(null, 204);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        [$sessionId, $tokenId] = $this->current($request);
        $this->sessions->revokeAll($request->user(), $sessionId, $tokenId);
        $this->audit->security('auth.session.revoked_others', $request->user());

        return response()->json(null, 204);
    }

    /**
     * The user's own security activity (logins, MFA, password changes...).
     */
    public function securityEvents(Request $request): AnonymousResourceCollection
    {
        $events = AuditLog::query()
            ->where('user_id', $request->user()->getKey())
            ->where('category', 'security')
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return AuditLogResource::collection($events);
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function current(Request $request): array
    {
        $token = $request->user()->currentAccessToken();

        return [
            $request->hasSession() ? $request->session()->getId() : null,
            $token instanceof PersonalAccessToken ? $token->id : null,
        ];
    }
}
