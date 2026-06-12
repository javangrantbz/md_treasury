<?php
declare(strict_types=1);

final class ErrorHandler
{
    /**
     * Standardized API error response handler.
     */
    public static function handle(Throwable $e, int $statusCode = 500): void
    {
        $traceId = bin2hex(random_bytes(8));

        // In production, log the actual exception details
        error_log("[Trace ID: $traceId] " . $e->getMessage() . "\n" . $e->getTraceAsString());

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success'  => false,
            'code'     => 'INTERNAL_SERVER_ERROR',
            'message'  => 'An unexpected error occurred. Please contact support.',
            'trace_id' => $traceId
        ]);
        exit;
    }
}
