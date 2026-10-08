<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Registry;

use PrestoWorld\Modules\HeadlessCMS\Registry\ResourceRegistry;
use PrestoWorld\Modules\HeadlessCMS\Tests\Support\FakeBlogProvider;
use PrestoWorld\Modules\HeadlessCMS\Tests\Support\FakeProvider;
use PrestoWorld\Modules\HeadlessCMS\Tests\TestCase;

final class ResourceRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeProvider::reset();
    }

    public function test_register_instance_is_returned(): void
    {
        $registry = new ResourceRegistry();
        $provider = new FakeProvider('shop');
        $registry->register($provider);

        self::assertSame($provider, $registry->get('shop'));
        self::assertSame(1, FakeProvider::$instances);
    }

    public function test_class_string_is_not_instantiated_until_requested(): void
    {
        $registry = new ResourceRegistry(static fn (string $class): FakeProvider => new $class());

        $registry->registerClass('shop', FakeProvider::class);
        $registry->registerClass('blog', FakeBlogProvider::class);

        self::assertSame(0, FakeProvider::$instances, 'registerClass must not instantiate providers');

        $registry->get('shop');

        self::assertSame(1, FakeProvider::$instances);
        self::assertSame(1, FakeProvider::$instancesByNamespace['shop']);
        self::assertArrayNotHasKey('blog', FakeProvider::$instancesByNamespace);
    }

    public function test_resolved_class_is_cached(): void
    {
        $registry = new ResourceRegistry(static fn (string $class): FakeProvider => new $class());
        $registry->registerClass('shop', FakeProvider::class);

        $first = $registry->get('shop');
        $second = $registry->get('shop');

        self::assertSame($first, $second);
        self::assertSame(1, FakeProvider::$instances);
    }

    public function test_get_unknown_returns_null(): void
    {
        $registry = new ResourceRegistry();

        self::assertNull($registry->get('missing'));
    }

    public function test_all_instantiates_every_registered_provider(): void
    {
        $registry = new ResourceRegistry(static fn (string $class): FakeProvider => new $class());
        $registry->registerClass('shop', FakeProvider::class);
        $registry->registerClass('blog', FakeBlogProvider::class);

        $providers = $registry->all();

        self::assertCount(2, $providers);
        self::assertSame(2, FakeProvider::$instances);
        self::assertSame(['shop', 'blog'], array_keys($providers));
    }

    public function test_namespaces_lists_keys_before_instantiation(): void
    {
        $registry = new ResourceRegistry(static fn (string $class): FakeProvider => new $class());
        $registry->registerClass('shop', FakeProvider::class);
        $registry->registerClass('blog', FakeBlogProvider::class);

        self::assertSame(['shop', 'blog'], $registry->namespaces());
        self::assertSame(0, FakeProvider::$instances);
    }
}