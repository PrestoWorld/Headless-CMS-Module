<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Support;

use PrestoWorld\Modules\HeadlessCMS\Http\Query;
use PrestoWorld\Modules\HeadlessCMS\Resource\AbstractResourceHandler;

final class FakeResourceHandler extends AbstractResourceHandler
{
    /** @var array<string, array<string, mixed>> */
    public array $items;

    public int $listCalls = 0;

    /**
     * @param array<string, array<string, mixed>> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function list(Query $query): array
    {
        $this->listCalls++;

        $items = array_values($this->items);
        $total = count($items);

        return [
            'items' => array_slice($items, $query->offset, $query->limit),
            'total' => $total,
        ];
    }

    public function read(string $key): ?array
    {
        return $this->items[$key] ?? null;
    }

    public function create(array $data): array
    {
        $key = (string) ($data['id'] ?? ('id' . (count($this->items) + 1)));
        $data['id'] = $key;
        $this->items[$key] = $data;

        return $data;
    }

    public function update(string $key, array $data): ?array
    {
        if (!isset($this->items[$key])) {
            return null;
        }

        $this->items[$key] = array_merge($this->items[$key], $data);

        return $this->items[$key];
    }

    public function delete(string $key): bool
    {
        if (!isset($this->items[$key])) {
            return false;
        }

        unset($this->items[$key]);

        return true;
    }
}