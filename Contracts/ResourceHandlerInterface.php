<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Contracts;

use PrestoWorld\Modules\HeadlessCMS\Http\Query;

/**
 * Adapter between a generic REST resource and an underlying data source.
 * Implementations only need to override the operations they support
 * (see {@see \PrestoWorld\Modules\HeadlessCMS\Resource\AbstractResourceHandler}).
 */
interface ResourceHandlerInterface
{
    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(Query $query): array;

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $key): ?array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public function update(string $key, array $data): ?array;

    public function delete(string $key): bool;
}