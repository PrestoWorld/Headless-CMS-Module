<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Support;

use PrestoWorld\Modules\HeadlessCMS\Contracts\ApiKeyRepositoryInterface;

final class InMemoryApiKeyRepository implements ApiKeyRepositoryInterface
{
    /** @var array<string, array<string, mixed>> keyed by 8-char prefix */
    private array $rows = [];

    /**
     * @param array<string, mixed> $overrides
     */
    public function add(string $secret, array $overrides = []): void
    {
        $this->rows[substr($secret, 0, 8)] = array_merge([
            'key_hash' => hash('sha256', $secret),
            'status' => 1,
            'scopes' => ['*'],
            'expires_at' => 0,
        ], $overrides);
    }

    public function findByPrefix(string $prefix): ?array
    {
        return $this->rows[$prefix] ?? null;
    }
}