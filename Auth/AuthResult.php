<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Auth;

final class AuthResult
{
    /**
     * @param array<string, mixed>|null $key
     */
    private function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly string $code,
        public readonly string $message,
        public readonly ?array $key,
    ) {
    }

    /**
     * @param array<string, mixed>|null $key
     */
    public static function allow(?array $key = null): self
    {
        return new self(true, 200, '', '', $key);
    }

    public static function deny(int $status, string $code, string $message): self
    {
        return new self(false, $status, $code, $message, null);
    }
}