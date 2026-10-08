<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Support;

use PrestoWorld\Modules\HeadlessCMS\Contracts\ProviderInterface;

class FakeProvider implements ProviderInterface
{
    public static int $instances = 0;

    /** @var array<string, int> */
    public static array $instancesByNamespace = [];

    /**
     * @param array<string, \PrestoWorld\Modules\HeadlessCMS\Resource\ResourceDefinition> $resources
     */
    public function __construct(
        private string $namespace = 'shop',
        private array $resources = [],
    ) {
        self::$instances++;
        self::$instancesByNamespace[$this->namespace] = (self::$instancesByNamespace[$this->namespace] ?? 0) + 1;
    }

    public static function reset(): void
    {
        self::$instances = 0;
        self::$instancesByNamespace = [];
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function resources(): array
    {
        return $this->resources;
    }
}