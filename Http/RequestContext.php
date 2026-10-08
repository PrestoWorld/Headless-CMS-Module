<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Http;

use Witals\Framework\Http\Request;

/**
 * Immutable snapshot of an incoming headless request.
 */
final class RequestContext
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly string $method,
        public readonly ?string $namespace,
        public readonly ?string $resource,
        public readonly ?string $key,
        public readonly array $query,
        public readonly array $body,
        public readonly ?string $apiKey,
    ) {
    }

    public static function fromRequest(
        Request $request,
        ?string $namespace,
        ?string $resource,
        ?string $key,
        ?string $apiKey,
    ): self {
        $query = $request->query();
        $query = is_array($query) ? $query : [];

        return new self(
            strtoupper($request->method()),
            self::clean($namespace),
            self::clean($resource),
            self::clean($key),
            $query,
            self::decodeBody($request),
            self::clean($apiKey),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeBody(Request $request): array
    {
        $raw = $request->body();

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        $post = $request->post();

        if (is_array($post)) {
            /** @var array<string, mixed> $post */
            return $post;
        }

        return [];
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}