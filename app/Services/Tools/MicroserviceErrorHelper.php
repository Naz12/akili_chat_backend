<?php

namespace App\Services\Tools;

/**
 * Normalize microservice errors into user-friendly messages.
 * Use for connection/timeout/HTTP errors so the API returns graceful messages.
 */
class MicroserviceErrorHelper
{
    /** User-facing message when the service is unreachable or times out. */
    public const SERVICE_UNAVAILABLE = 'The service is temporarily unavailable. Please try again later.';

    /** User-facing message when the service returns an error. */
    public const SERVICE_ERROR = 'The service encountered an error. Please try again.';

    /**
     * Return a user-friendly error string for API responses.
     * Logs the original message; returns a safe message for the client.
     */
    public static function userMessage(string $serviceName, string $originalError, ?\Throwable $e = null): string
    {
        $lower = strtolower($originalError);
        $isConnection = str_contains($lower, 'connection')
            || str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'refused')
            || str_contains($lower, 'could not resolve')
            || str_contains($lower, 'failed to open stream')
            || str_contains($lower, 'network')
            || ($e && $e instanceof \Illuminate\Http\Client\ConnectionException);

        if ($isConnection) {
            return $serviceName . ' is temporarily unavailable. Please try again later.';
        }

        // If the microservice returned a short, safe message (e.g. from their API), use it; otherwise generic.
        $trimmed = trim($originalError);
        if ($trimmed === '' || $trimmed === '{}') {
            return $serviceName . ' encountered an error. Please try again.';
        }
        // JSON-only errors (e.g. {"detail":""} from FastAPI): return generic message
        if (preg_match('/^\s*\{.*\}\s*$/s', $trimmed)) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $msg = $decoded['detail'] ?? $decoded['message'] ?? $decoded['error'] ?? null;
                if (is_string($msg) && $msg !== '' && strlen($msg) <= 200) {
                    return $msg;
                }
            }
            return $serviceName . ' encountered an error. Please try again.';
        }
        if (strlen($trimmed) <= 200 && ! str_contains($trimmed, '<')) {
            return $trimmed;
        }
        return $serviceName . ' encountered an error. Please try again.';
    }
}
