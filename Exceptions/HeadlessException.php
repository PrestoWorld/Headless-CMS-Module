<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Exceptions;

use PrestoWorld\Modules\HeadlessCMS\Http\ErrorCode;
use RuntimeException;

final class HeadlessException extends RuntimeException
{
    /**
     * @param list<string> $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $what): self
    {
        return new self(ErrorCode::NOT_FOUND, $what . ' not found', 404);
    }

    public static function badRequest(string $message): self
    {
        return new self(ErrorCode::BAD_REQUEST, $message, 400);
    }

    public static function methodNotAllowed(string $operation): self
    {
        return new self(ErrorCode::METHOD_NOT_ALLOWED, 'Operation not allowed: ' . $operation, 405);
    }
}