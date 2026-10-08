<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Http;

/**
 * Normalised pagination / filtering / sorting extracted from the query string.
 *
 * Supported keys: page, limit|size, offset, q|search, sort and any other key
 * is forwarded verbatim as a filter.
 */
final class Query
{
    private const RESERVED = ['limit', 'size', 'offset', 'page', 'q', 'search', 'sort'];

    /**
     * @param array<string, mixed>  $filters
     * @param array<string, string> $sort
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly array $filters = [],
        public readonly array $sort = [],
        public readonly ?string $search = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromArray(array $query, int $defaultSize, int $maxSize): self
    {
        $limit = self::intOf($query['limit'] ?? $query['size'] ?? null, $defaultSize);
        $limit = max(1, min($limit, $maxSize));

        $offset = max(0, self::intOf($query['offset'] ?? null, 0));

        if ($offset === 0 && isset($query['page'])) {
            $page = max(1, self::intOf($query['page'], 1));
            $offset = ($page - 1) * $limit;
        }

        return new self(
            $limit,
            $offset,
            self::filters($query),
            self::parseSort($query['sort'] ?? null),
            self::stringOf($query['q'] ?? $query['search'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private static function filters(array $query): array
    {
        $filters = [];
        foreach ($query as $key => $value) {
            if (!in_array($key, self::RESERVED, true)) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * @return array<string, string>
     */
    private static function parseSort(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $sort = [];

        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $direction = 'ASC';

            if (str_starts_with($part, '-')) {
                $direction = 'DESC';
                $part = substr($part, 1);
            } elseif (str_contains($part, ':')) {
                [$field, $dir] = explode(':', $part, 2);
                $part = $field;
                $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
            }

            if ($part !== '') {
                $sort[$part] = $direction;
            }
        }

        return $sort;
    }

    private static function intOf(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private static function stringOf(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}