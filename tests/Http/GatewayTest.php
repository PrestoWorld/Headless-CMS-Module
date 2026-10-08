<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Http;

use PrestoWorld\Modules\HeadlessCMS\Auth\ApiKeyAuthenticator;
use PrestoWorld\Modules\HeadlessCMS\Http\ErrorCode;
use PrestoWorld\Modules\HeadlessCMS\Http\Gateway;
use PrestoWorld\Modules\HeadlessCMS\Http\RequestContext;
use PrestoWorld\Modules\HeadlessCMS\Registry\ResourceRegistry;
use PrestoWorld\Modules\HeadlessCMS\Resource\ResourceDefinition;
use PrestoWorld\Modules\HeadlessCMS\Tests\Support\FakeProvider;
use PrestoWorld\Modules\HeadlessCMS\Tests\Support\FakeResourceHandler;
use PrestoWorld\Modules\HeadlessCMS\Tests\Support\InMemoryApiKeyRepository;
use PrestoWorld\Modules\HeadlessCMS\Tests\TestCase;
use Witals\Framework\Http\Response;

final class GatewayTest extends TestCase
{
    private const SECRET = 'hls_secret_1234567890';
    private const ALL_OPS = ['list', 'read', 'create', 'update', 'delete'];

    private function registry(FakeResourceHandler $handler, array $operations = self::ALL_OPS, bool $publicRead = true): ResourceRegistry
    {
        $registry = new ResourceRegistry();
        $registry->register(new FakeProvider('shop', [
            'products' => new ResourceDefinition('products', $handler, 'id', $operations, $publicRead),
        ]));

        return $registry;
    }

    private function gateway(
        ResourceRegistry $registry,
        ?ApiKeyAuthenticator $auth = null,
        bool $authRequired = true,
        ?\Closure $handlerResolver = null,
    ): Gateway {
        return new Gateway($registry, $auth, 20, 100, $authRequired, 'X-API-Key', $handlerResolver);
    }

    private function authenticator(): ApiKeyAuthenticator
    {
        $repo = new InMemoryApiKeyRepository();
        $repo->add(self::SECRET, ['scopes' => ['*']]);

        return new ApiKeyAuthenticator($repo);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    private function ctx(
        string $method = 'GET',
        ?string $namespace = 'shop',
        ?string $resource = 'products',
        ?string $key = null,
        array $query = [],
        array $body = [],
        ?string $apiKey = null,
    ): RequestContext {
        return new RequestContext($method, $namespace, $resource, $key, $query, $body, $apiKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $decoded = json_decode($response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function test_discovery_lists_providers(): void
    {
        $registry = $this->registry(new FakeResourceHandler());
        $response = $this->gateway($registry)->handle($this->ctx('GET', null, null, null));

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->payload($response);
        self::assertArrayHasKey('data', $payload);
        self::assertArrayHasKey('shop', $payload['data']['namespaces']);
        self::assertArrayHasKey('products', $payload['data']['namespaces']['shop']['resources']);
    }

    public function test_describe_namespace(): void
    {
        $registry = $this->registry(new FakeResourceHandler());
        $response = $this->gateway($registry)->handle($this->ctx('GET', 'shop', null, null));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('products', $this->payload($response)['data']['resources']['products']['name']);
    }

    public function test_public_list_works_without_key_and_does_not_touch_auth(): void
    {
        $handler = new FakeResourceHandler(['1' => ['id' => '1', 'name' => 'A']]);
        $registry = $this->registry($handler);

        $gateway = $this->gateway($registry, null, true);
        $response = $gateway->handle($this->ctx('GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->payload($response)['meta']['total']);
        self::assertSame(1, $handler->listCalls);
    }

    public function test_public_read_returns_item_and_404_for_missing(): void
    {
        $handler = new FakeResourceHandler(['1' => ['id' => '1', 'name' => 'A']]);
        $registry = $this->registry($handler);
        $gateway = $this->gateway($registry, null, true);

        $ok = $gateway->handle($this->ctx('GET', 'shop', 'products', '1'));
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame('A', $this->payload($ok)['data']['name']);

        $missing = $gateway->handle($this->ctx('GET', 'shop', 'products', '404'));
        self::assertSame(404, $missing->getStatusCode());
        self::assertSame(ErrorCode::NOT_FOUND, $this->payload($missing)['error']['code']);
    }

    public function test_unknown_namespace_returns_404(): void
    {
        $registry = $this->registry(new FakeResourceHandler());
        $response = $this->gateway($registry)->handle($this->ctx('GET', 'nope', 'products'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_unknown_resource_returns_404(): void
    {
        $registry = $this->registry(new FakeResourceHandler());
        $response = $this->gateway($registry)->handle($this->ctx('GET', 'shop', 'unknown'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_write_without_key_returns_401(): void
    {
        $registry = $this->registry(new FakeResourceHandler());
        $response = $this->gateway($registry, $this->authenticator())
            ->handle($this->ctx('POST', 'shop', 'products', null, [], ['name' => 'New']));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(ErrorCode::UNAUTHORIZED, $this->payload($response)['error']['code']);
    }

    public function test_write_with_wrong_scope_returns_403(): void
    {
        $repo = new InMemoryApiKeyRepository();
        $repo->add(self::SECRET, ['scopes' => ['other:thing:write']]);

        $registry = $this->registry(new FakeResourceHandler());
        $response = $this->gateway($registry, new ApiKeyAuthenticator($repo))
            ->handle($this->ctx('POST', 'shop', 'products', null, [], ['name' => 'New'], self::SECRET));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(ErrorCode::FORBIDDEN, $this->payload($response)['error']['code']);
    }

    public function test_create_with_key_returns_201(): void
    {
        $registry = $this->registry(new FakeResourceHandler());
        $response = $this->gateway($registry, $this->authenticator())
            ->handle($this->ctx('POST', 'shop', 'products', null, [], ['id' => '7', 'name' => 'New'], self::SECRET));

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('New', $this->payload($response)['data']['name']);
    }

    public function test_update_and_delete_with_key(): void
    {
        $handler = new FakeResourceHandler(['1' => ['id' => '1', 'name' => 'A']]);
        $registry = $this->registry($handler);
        $gateway = $this->gateway($registry, $this->authenticator());

        $updated = $gateway->handle($this->ctx('PUT', 'shop', 'products', '1', [], ['name' => 'B'], self::SECRET));
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame('B', $this->payload($updated)['data']['name']);

        $deleted = $gateway->handle($this->ctx('DELETE', 'shop', 'products', '1', [], [], self::SECRET));
        self::assertSame(200, $deleted->getStatusCode());
        self::assertTrue($this->payload($deleted)['data']['deleted']);
    }

    public function test_unsupported_operation_returns_405(): void
    {
        $registry = $this->registry(new FakeResourceHandler(), ['list', 'read']);
        $response = $this->gateway($registry, $this->authenticator())
            ->handle($this->ctx('POST', 'shop', 'products', null, [], ['name' => 'New'], self::SECRET));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame(ErrorCode::METHOD_NOT_ALLOWED, $this->payload($response)['error']['code']);
    }

    public function test_pagination_meta_is_returned(): void
    {
        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[(string) $i] = ['id' => (string) $i];
        }

        $registry = $this->registry(new FakeResourceHandler($items));
        $response = $this->gateway($registry, null, true)
            ->handle($this->ctx('GET', 'shop', 'products', null, ['limit' => 2, 'page' => 2]));

        $payload = $this->payload($response);
        self::assertCount(2, $payload['data']);
        self::assertSame(5, $payload['meta']['total']);
        self::assertSame(2, $payload['meta']['offset']);
    }

    public function test_class_string_handler_is_resolved_lazily(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(new FakeProvider('shop', [
            'products' => new ResourceDefinition('products', FakeResourceHandler::class, 'id'),
        ]));

        $resolved = 0;
        $resolver = function (string $class) use (&$resolved): FakeResourceHandler {
            $resolved++;

            return new FakeResourceHandler(['1' => ['id' => '1', 'name' => 'A']]);
        };

        $gateway = $this->gateway($registry, null, true, $resolver);

        $response = $gateway->handle($this->ctx('GET', 'shop', 'products', '1'));

        self::assertSame('A', $this->payload($response)['data']['name']);
        self::assertSame(1, $resolved);
    }

    public function test_missing_handler_resolution_returns_500(): void
    {
        $registry = new ResourceRegistry();
        $registry->register(new FakeProvider('shop', [
            'products' => new ResourceDefinition('products', FakeResourceHandler::class),
        ]));

        $response = $this->gateway($registry, null, true)->handle($this->ctx('GET'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(ErrorCode::INTERNAL_ERROR, $this->payload($response)['error']['code']);
    }
}