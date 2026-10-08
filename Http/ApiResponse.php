<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Http;

use Witals\Framework\Http\Response;

/**
 * JSON envelope helpers shared by the gateway.
 *
 * Success: { "data": ..., "meta": {...} }
 * Error:   { "error": { "code": ..., "message": ..., "details": [...] } }
 */
final class ApiResponse
{
    /**
     * @param array<string, mixed> $meta
     */
    public static function data(mixed $data, array $meta = [], int $status = 200): Response
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return Response::json($payload, $status);
    }

    /**
     * @param list<string> $details
     */
    public static function error(string $code, string $message, array $details = [], int $status = 400): Response
    {
        return Response::json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}