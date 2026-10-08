<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Resource;

use PrestoWorld\Modules\HeadlessCMS\Contracts\ResourceHandlerInterface;
use PrestoWorld\Modules\HeadlessCMS\Exceptions\HeadlessException;
use PrestoWorld\Modules\HeadlessCMS\Http\Query;

/**
 * Convenience base class: only override the operations a resource exposes.
 * Unsupported operations fail with a clear 405 response.
 */
abstract class AbstractResourceHandler implements ResourceHandlerInterface
{
    public function list(Query $query): array
    {
        throw HeadlessException::methodNotAllowed('list');
    }

    public function read(string $key): ?array
    {
        throw HeadlessException::methodNotAllowed('read');
    }

    public function create(array $data): array
    {
        throw HeadlessException::methodNotAllowed('create');
    }

    public function update(string $key, array $data): ?array
    {
        throw HeadlessException::methodNotAllowed('update');
    }

    public function delete(string $key): bool
    {
        throw HeadlessException::methodNotAllowed('delete');
    }
}