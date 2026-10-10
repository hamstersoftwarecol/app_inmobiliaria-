<?php

namespace App\Application\Audit;

use App\Domain\Audit\AuditCategory;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Central writer for the audit trail. Never stores secrets: callers pass only
 * the attributes that changed, and sensitive keys are scrubbed defensively.
 */
class AuditLogger
{
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token',
        'mfa_secret', 'mfa_recovery_codes', 'credentials', 'remember_token', 'code',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        AuditCategory $category,
        string $event,
        ?User $actor = null,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        array $metadata = [],
    ): AuditLog {
        $actor ??= $this->request->user();

        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'category' => $category,
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old !== null ? $this->scrub($old) : null,
            'new_values' => $new !== null ? $this->scrub($new) : null,
            'metadata' => $metadata !== [] ? $this->scrub($metadata) : null,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 1000) ?: null,
            'request_id' => $this->request->attributes->get('request_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function security(string $event, ?User $actor = null, array $metadata = []): AuditLog
    {
        return $this->log(AuditCategory::Security, $event, $actor, $actor, metadata: $metadata);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function scrub(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->scrub($value);
            }
        }

        return $values;
    }
}
