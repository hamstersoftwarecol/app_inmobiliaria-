<?php

namespace App\Http;

use App\Domain\Shared\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every API error with the same envelope:
 * {"error": {"code": string, "message": string, "details": object|null, "request_id": string|null}}
 */
class ApiErrorRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        [$status, $code, $message, $details, $headers] = $this->describe($e);

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
                'request_id' => $request->attributes->get('request_id'),
            ],
        ], $status, $headers);
    }

    /**
     * @return array{int, string, string, array<string, mixed>, array<string, string>}
     */
    private function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof DomainException => [
                $e->status, $e->errorCode, $e->getMessage(), $e->details,
                isset($e->details['retry_after']) ? ['Retry-After' => (string) $e->details['retry_after']] : [],
            ],
            $e instanceof ValidationException => [
                422, 'validation_failed', 'The given data was invalid.', ['fields' => $e->errors()], [],
            ],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Authentication is required.', [], []],
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => [
                403, 'forbidden', 'You are not authorized to perform this action.', [], [],
            ],
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => [404, 'not_found', 'The requested resource was not found.', [], []],
            $e instanceof TokenMismatchException => [419, 'csrf_token_mismatch', 'The CSRF token is missing or invalid. Refresh and try again.', [], []],
            $e instanceof ThrottleRequestsException => [
                429, 'too_many_requests', 'Too many requests. Please slow down.', [],
                array_intersect_key($e->getHeaders(), ['Retry-After' => true]),
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(), $e->getStatusCode() === 419 ? 'csrf_token_mismatch' : 'http_error',
                $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.', [], [],
            ],
            default => [
                500, 'server_error',
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred. Please try again later.',
                [], [],
            ],
        };
    }
}
