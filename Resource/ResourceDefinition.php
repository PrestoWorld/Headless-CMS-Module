<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Resource;

use PrestoWorld\Modules\HeadlessCMS\Contracts\ResourceHandlerInterface;

/**
 * Describes a single REST resource exposed by a provider.
 *
 * The handler may be given either as an already-built instance or as a
 * container class-string. In the latter case it is only resolved when that
 * resource is actually requested.
 */
final class ResourceDefinition
{
    /**
     * @param ResourceHandlerInterface|class-string<ResourceHandlerInterface> $handler
     * @param list<string>                                                    $operations
     */
    public function __construct(
        public readonly string $name,
        public readonly ResourceHandlerInterface|string $handler,
        public readonly string $key = 'id',
        public readonly array $operations = ['list', 'read', 'create', 'update', 'delete'],
        public readonly bool $publicRead = true,
        public readonly ?string $writeScope = null,
    ) {
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    public function writeScopeFor(string $namespace): string
    {
        return $this->writeScope ?? $namespace . ':' . $this->name . ':write';
    }

    public function readScopeFor(string $namespace): string
    {
        return $namespace . ':' . $this->name . ':read';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'key' => $this->key,
            'operations' => $this->operations,
            'public_read' => $this->publicRead,
        ];
    }
}