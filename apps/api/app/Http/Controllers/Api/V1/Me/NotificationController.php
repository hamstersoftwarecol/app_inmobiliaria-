<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Audit\AuditLogger;
use App\Application\Notifications\NotificationPreferences;
use App\Domain\Audit\AuditCategory;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Domain\Shared\DomainException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** In-app notification center and e-mail preferences of the signed-in user. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['unread' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $page = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate($f['per_page'] ?? 20);

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['count' => $request->user()->unreadNotifications()->count()]]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return $this->unreadCount($request);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->unreadCount($request);
    }

    public function preferences(Request $request, NotificationPreferences $preferences): JsonResponse
    {
        return response()->json(['data' => $preferences->all($request->user())]);
    }

    public function updatePreferences(Request $request, NotificationPreferences $preferences, AuditLogger $audit): JsonResponse
    {
        $allowed = array_map(fn ($e) => $e->value, NotificationEvent::cases());
        $data = $request->validate([
            'mail' => ['required', 'array', 'min:1', function ($attr, $value, $fail) use ($allowed) {
                if (is_array($value) && array_diff(array_keys($value), $allowed) !== []) {
                    $fail('Unknown notification category.');
                }
            }],
            'mail.*' => ['boolean'],
        ]);

        $user = $request->user();
        $before = $preferences->all($user);
        foreach ($data['mail'] as $event => $on) {
            $preferences->set($user, NotificationEvent::from($event), (bool) $on);
        }
        // Marketing consent is recorded in the audit trail (who, when, from where).
        if (isset($data['mail']['marketing']) && (bool) $data['mail']['marketing'] !== $before['marketing']['mail']) {
            $audit->log(AuditCategory::Data, 'notifications.marketing_consent', $user, $user, ['mail' => $before['marketing']['mail']], ['mail' => (bool) $data['mail']['marketing']]);
        }

        return response()->json(['data' => $preferences->all($user)]);
    }

    /**
     * One-click unsubscribe from an e-mail link (no sign-in; the token identifies user and category).
     * Accepts the token in the body or the query string (RFC 8058 List-Unsubscribe-Post).
     */
    public function unsubscribe(Request $request, NotificationPreferences $preferences): JsonResponse
    {
        $token = (string) ($request->input('token') ?? $request->query('token'));
        $resolved = $token !== '' ? $preferences->resolveToken($token) : null;
        if ($resolved === null) {
            throw new DomainException('invalid_token', 'This unsubscribe link is invalid.', 422);
        }
        [$user, $event] = $resolved;
        $preferences->set($user, $event, false);

        return response()->json(['message' => 'You have been unsubscribed.', 'event' => $event->value]);
    }
}
