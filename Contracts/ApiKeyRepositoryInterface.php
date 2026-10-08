<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Contracts;

interface ApiKeyRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByPrefix(string $prefix): ?array;
}