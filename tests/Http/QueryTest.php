<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Http;

use PrestoWorld\Modules\HeadlessCMS\Http\Query;
use PrestoWorld\Modules\HeadlessCMS\Tests\TestCase;

final class QueryTest extends TestCase
{
    public function test_defaults(): void
    {
        $query = Query::fromArray([], 20, 100);

        self::assertSame(20, $query->limit);
        self::assertSame(0, $query->offset);
        self::assertSame([], $query->filters);
        self::assertSame([], $query->sort);
        self::assertNull($query->search);
    }

    public function test_limit_is_clamped_to_max_size(): void
    {
        self::assertSame(100, Query::fromArray(['limit' => 500], 20, 100)->limit);
        self::assertSame(1, Query::fromArray(['limit' => 0], 20, 100)->limit);
    }

    public function test_size_alias_is_supported(): void
    {
        self::assertSame(5, Query::fromArray(['size' => '5'], 20, 100)->limit);
    }

    public function test_page_translates_to_offset(): void
    {
        $query = Query::fromArray(['page' => 3, 'limit' => 10], 20, 100);

        self::assertSame(20, $query->offset);
    }

    public function test_explicit_offset_wins_over_page(): void
    {
        $query = Query::fromArray(['page' => 3, 'offset' => 7], 20, 100);

        self::assertSame(7, $query->offset);
    }

    public function test_sort_parsing(): void
    {
        $query = Query::fromArray(['sort' => '-created_at,price:asc,name'], 20, 100);

        self::assertSame([
            'created_at' => 'DESC',
            'price' => 'ASC',
            'name' => 'ASC',
        ], $query->sort);
    }

    public function test_unknown_keys_become_filters(): void
    {
        $query = Query::fromArray(['status' => 'active', 'limit' => 5], 20, 100);

        self::assertSame(['status' => 'active'], $query->filters);
    }

    public function test_search_alias(): void
    {
        self::assertSame('phone', Query::fromArray(['search' => 'phone'], 20, 100)->search);
        self::assertSame('phone', Query::fromArray(['q' => 'phone'], 20, 100)->search);
    }
}