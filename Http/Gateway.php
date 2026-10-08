<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Http;

use PrestoWorld\Modules\HeadlessCMS\Auth\ApiKeyAuthenticator;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ProviderInterface;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ResourceHandlerInterface;
use PrestoWorld\Modules\HeadlessCMS\Exceptions\HeadlessException;
use PrestoWorld\Modules\HeadlessCMS\Registry\ResourceRegistry;
use PrestoWorld\Modules\HeadlessCMS\Resource\ResourceDefinition;
use Throwable;
use Witals\Framework\Http\Response;

/**
 * Resolves the requested namespace/resource, authenticates writes and executes
 * the matching operation. Nothing beyond the requested provider, resource and
 * (for writes) the API key store is instantiated.
 */
final class Gateway
{
    /** @var \Closure(): (ApiKeyAuthenticator|null) */
    private \Closure $authFactory;

    /** @var (\Closure(class-string<ResourceHandlerInterface>): (object|null))|null */
    private ?\Closure $handlerResolver;

    /**
     * @param (\Closure(): (ApiKeyAuthenticator|null))|ApiKeyAuthenticator|null $auth
     * @param (\Closure(class-string<ResourceHandlerInterface>): (object|null))|null $handlerResolver
     */
    public function __construct(
        private ResourceRegistry $registry,
        \Closure|ApiKeyAuthenticator|null $auth = null,
        private int $defaultSize = 20,
        private int $maxSize = 100,
        private bool $authRequired = true,
        private string $authHeader = 'X-API-Key',
        ?\Closure $handlerResolver = null,
    ) {
        $this->authFactory = match (true) {
            $auth instanceof ApiKeyAuthenticator => static fn (): ApiKeyAuthenticator => $auth,
            $auth instanceof \Closure => $auth,
            default => static fn (): ?ApiKeyAuthenticator => null,
        };

        $this->handlerResolver = $handlerResolver;
    }

    public function apiKeyHeader(): string
    {
        return $this->authHeader;
    }

    public function handle(RequestContext $context): Response
    {
        try {
            return $this->dispatch($context);
        } catch (HeadlessException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->details, $e->status);
        } catch (Throwable $e) {
            return ApiResponse::error(ErrorCode::INTERNAL_ERROR, 'Internal server error', [$e->getMessage()], 500);
        }
    }

    private function dispatch(RequestContext $context): Response
    {
        if ($context->namespace === null) {
            return $this->discovery();
        }

        $provider = $this->registry->get($context->namespace);
        if ($provider === null) {
            throw HeadlessException::notFound('Namespace ' . $context->namespace);
        }

        if ($context->resource === null) {
            return $this->describe($provider);
        }

        $definition = $provider->resources()[$context->resource] ?? null;
        if ($definition === null) {
            throw HeadlessException::notFound('Resource ' . $context->resource);
        }

        $operation = $this->operationFor($context);

        if (!$definition->supports($operation)) {
            throw HeadlessException::methodNotAllowed($operation);
        }

        $this->authorize($context, $provider->namespace(), $definition, $operation);

        return $this->execute($context, $definition, $operation);
    }

    private function discovery(): Response
    {
        $namespaces = [];

        foreach ($this->registry->all() as $namespace => $provider) {
            $resources = [];
            foreach ($provider->resources() as $name => $definition) {
                $resources[$name] = $definition->toArray();
            }
            $namespaces[$namespace] = ['name' => $namespace, 'resources' => $resources];
        }

        return ApiResponse::data([
            'name' => 'headless-cms',
            'version' => '1.0.0',
            'namespaces' => $namespaces,
        ]);
    }

    private function describe(ProviderInterface $provider): Response
    {
        $resources = [];
        foreach ($provider->resources() as $name => $definition) {
            $resources[$name] = $definition->toArray();
        }

        return ApiResponse::data([
            'name' => $provider->namespace(),
            'resources' => $resources,
        ]);
    }

    private function authorize(
        RequestContext $context,
        string $namespace,
        ResourceDefinition $definition,
        string $operation,
    ): void {
        if (!$this->authRequired) {
            return;
        }

        $isWrite = $operation !== 'list' && $operation !== 'read';

        if (!$isWrite && $definition->publicRead) {
            return;
        }

        $scope = $isWrite
            ? $definition->writeScopeFor($namespace)
            : $definition->readScopeFor($namespace);

        $authenticator = ($this->authFactory)();
        if ($authenticator === null) {
            throw new HeadlessException(ErrorCode::INTERNAL_ERROR, 'API key store is not configured.', 500);
        }

        $result = $authenticator->authenticate($context->apiKey, $scope);

        if (!$result->ok) {
            throw new HeadlessException($result->code, $result->message, $result->status);
        }
    }

    private function execute(
        RequestContext $context,
        ResourceDefinition $definition,
        string $operation,
    ): Response {
        $handler = $this->handlerFor($definition);
        $key = $context->key;

        switch ($operation) {
            case 'list':
                $query = Query::fromArray($context->query, $this->defaultSize, $this->maxSize);
                $page = $handler->list($query);

                return ApiResponse::data($page['items'], [
                    'total' => $page['total'],
                    'limit' => $query->limit,
                    'offset' => $query->offset,
                ]);

            case 'read':
                if ($key === null) {
                    throw HeadlessException::badRequest('Missing resource key.');
                }
                $row = $handler->read($key);
                if ($row === null) {
                    throw HeadlessException::notFound($definition->name);
                }

                return ApiResponse::data($row);

            case 'create':
                return ApiResponse::data($handler->create($context->body), [], 201);

            case 'update':
                if ($key === null) {
                    throw HeadlessException::badRequest('Missing resource key.');
                }
                $row = $handler->update($key, $context->body);
                if ($row === null) {
                    throw HeadlessException::notFound($definition->name);
                }

                return ApiResponse::data($row);

            case 'delete':
                if ($key === null) {
                    throw HeadlessException::badRequest('Missing resource key.');
                }
                if (!$handler->delete($key)) {
                    throw HeadlessException::notFound($definition->name);
                }

                return ApiResponse::data(['deleted' => true]);

            default:
                throw HeadlessException::methodNotAllowed($operation);
        }
    }

    private function handlerFor(ResourceDefinition $definition): ResourceHandlerInterface
    {
        $handler = $definition->handler;

        if ($handler instanceof ResourceHandlerInterface) {
            return $handler;
        }

        if ($this->handlerResolver !== null) {
            $resolved = ($this->handlerResolver)($handler);
            if ($resolved instanceof ResourceHandlerInterface) {
                return $resolved;
            }
        }

        throw new HeadlessException(
            ErrorCode::INTERNAL_ERROR,
            'Resource handler is not resolvable: ' . $handler,
            500,
        );
    }

    private function operationFor(RequestContext $context): string
    {
        return match ($context->method) {
            'GET' => $context->key === null ? 'list' : 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'unsupported',
        };
    }
}