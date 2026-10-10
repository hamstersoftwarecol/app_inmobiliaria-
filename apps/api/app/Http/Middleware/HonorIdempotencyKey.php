<?php

namespace App\Http\Middleware;

use App\Domain\Showings\Exceptions\ShowingException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` for critical POSTs (see ADR/architecture overview): the first response for a
 * successful response for a key is stored for 24 hours and replayed for retries of the same request
 * by the same user, so a flaky connection cannot create two showings. Rejected requests store nothing.
 */
class HonorIdempotencyKey
{
    public const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();
        if ($key === null || $user === null || $request->method() !== 'POST') {
            return $next($request);
        }
        if (! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Use 8–100 letters, digits, dashes or underscores.']);
        }

        $hash = hash('sha256', $request->method().' '.$request->path().' '.json_encode($request->all()));
        $existing = DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)->where('expires_at', '>', now())->first();
        if ($existing !== null) {
            return $this->replay($existing, $hash);
        }

        DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)->delete(); // expired
        try {
            $id = DB::table('idempotency_keys')->insertGetId([
                'user_id' => $user->id, 'key' => $key, 'method' => $request->method(), 'path' => substr($request->path(), 0, 255),
                'request_hash' => $hash, 'created_at' => now(), 'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ShowingException::idempotencyInProgress();
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Rejected requests (validation, business rules, errors) store nothing: the key can be retried.
            DB::table('idempotency_keys')->where('id', $id)->delete();
            throw $e;
        }

        // Only successes are final; rejected requests (4xx/5xx) free the key for a corrected retry.
        if (! $response->isSuccessful()) {
            DB::table('idempotency_keys')->where('id', $id)->delete();
        } else {
            DB::table('idempotency_keys')->where('id', $id)->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent() ?: null,
            ]);
        }

        return $response;
    }

    private function replay(object $existing, string $hash): Response
    {
        if (! hash_equals($existing->request_hash, $hash)) {
            throw ShowingException::idempotencyMismatch();
        }
        if ($existing->response_status === null) {
            throw ShowingException::idempotencyInProgress();
        }

        return (new JsonResponse(json_decode((string) $existing->response_body, true), (int) $existing->response_status))
            ->header('Idempotent-Replayed', 'true');
    }
}
