<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS;

use App\Http\Routing\Contracts\RouteRegistryInterface;
use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\HeadlessCMS\Auth\ApiKeyAuthenticator;
use PrestoWorld\Modules\HeadlessCMS\Auth\ApiKeyRepository;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ApiKeyRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ProviderInterface;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ResourceHandlerInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\Gateway;
use PrestoWorld\Modules\HeadlessCMS\Http\HeadlessController;
use PrestoWorld\Modules\HeadlessCMS\Registry\ResourceRegistry;
use Witals\Framework\Application;
use Witals\Framework\Module\Module as WitalsModule;
use Witals\Framework\Module\ModuleDiscoveryService;

class Module extends WitalsModule
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        protected Application $app,
        protected string $path = '',
        protected array $metadata = [],
    ) {
        if ($path === '') {
            $path = __DIR__;
        }
        if ($metadata === []) {
            $metadata = ['name' => 'headless-cms'];
        }

        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        $this->app->singleton(ResourceRegistry::class, function (Application $app): ResourceRegistry {
            return new ResourceRegistry(function (string $class) use ($app): ?ProviderInterface {
                $provider = $app->make($class);

                return $provider instanceof ProviderInterface ? $provider : null;
            });
        });

        $this->app->singleton(ApiKeyRepositoryInterface::class, function (Application $app): ApiKeyRepositoryInterface {
            $db = $app->make(DatabaseInterface::class);
            if (!$db instanceof DatabaseInterface) {
                throw new \RuntimeException('DatabaseInterface is not bound in the container.');
            }

            return new ApiKeyRepository($db, $this->tablePrefix($app));
        });

        $this->app->singleton(ApiKeyAuthenticator::class, function (Application $app): ApiKeyAuthenticator {
            return new ApiKeyAuthenticator($this->resolve($app, ApiKeyRepositoryInterface::class));
        });

        $this->app->singleton(Gateway::class, function (Application $app): Gateway {
            // Auth is built lazily: public reads never touch the database.
            $authFactory = function () use ($app): ApiKeyAuthenticator {
                return $this->resolve($app, ApiKeyAuthenticator::class);
            };

            // Handlers are resolved on demand, only for the requested resource.
            $handlerResolver = function (string $class) use ($app): ?ResourceHandlerInterface {
                $handler = $app->make($class);

                return $handler instanceof ResourceHandlerInterface ? $handler : null;
            };

            return new Gateway(
                $this->resolve($app, ResourceRegistry::class),
                $authFactory,
                (int) ($app->config('headless-cms.pagination.default_size', 20) ?: 20),
                (int) ($app->config('headless-cms.pagination.max_size', 100) ?: 100),
                (bool) ($app->config('headless-cms.auth.required', true)),
                (string) ($app->config('headless-cms.auth.header', 'X-API-Key') ?: 'X-API-Key'),
                $handlerResolver,
            );
        });
    }

    public function boot(): void
    {
        $this->registerProviders();
        $this->registerRoutes();
        $this->registerConsoleCommands();
    }

    private function registerConsoleCommands(): void
    {
        if (!$this->app->has(\Witals\Framework\Console\Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(\Witals\Framework\Console\Kernel::class);
        if ($kernel instanceof \Witals\Framework\Console\Kernel) {
            $kernel->register(\PrestoWorld\Modules\HeadlessCMS\Console\ApiKeyCommand::class);
        }
    }

    public function getName(): string
    {
        return 'HeadlessCMS';
    }

    /**
     * Providers advertise themselves through the manifest "headless" key as a
     * namespace => class map, so the registry can lazily resolve exactly one
     * namespace per request without loading any other provider:
     *
     *   "headless": { "providers": { "ecommerce": "Vendor\\...\\EcommerceProvider" } }
     *
     * Only manifest strings are read here — no provider class is autoloaded or
     * instantiated until a matching request arrives.
     */
    private function registerProviders(): void
    {
        if (!$this->app->has(ModuleDiscoveryService::class)) {
            return;
        }

        $registry = $this->resolve($this->app, ResourceRegistry::class);
        $discovery = $this->resolve($this->app, ModuleDiscoveryService::class);
        $discovery->discover();

        foreach ($discovery->getMetadataMap() as $meta) {
            if (!is_array($meta)) {
                continue;
            }

            $headless = $meta['headless'] ?? null;
            if (!is_array($headless)) {
                continue;
            }

            $providers = $headless['providers'] ?? [];
            if (!is_array($providers)) {
                continue;
            }

            foreach ($providers as $namespace => $class) {
                if (is_string($namespace) && $namespace !== '' && is_string($class) && $class !== '') {
                    $registry->registerClass($namespace, $class);
                }
            }
        }
    }

    private function registerRoutes(): void
    {
        if (!$this->app->has(RouteRegistryInterface::class)) {
            return;
        }

        $cachePath = $this->app->storagePath('framework/cache/routes.php');
        if (is_file($cachePath)) {
            @unlink($cachePath);
        }

        $registry = $this->resolve($this->app, RouteRegistryInterface::class);
        $prefix = rtrim((string) ($this->app->config('headless-cms.prefix', '/api/headless') ?: '/api/headless'), '/');
        $action = [HeadlessController::class, 'handle'];

        $routes = [
            ['GET', $prefix],
            ['GET', $prefix . '/{namespace}'],
            ['GET', $prefix . '/{namespace}/{resource}'],
            ['POST', $prefix . '/{namespace}/{resource}'],
            ['GET', $prefix . '/{namespace}/{resource}/{key}'],
            ['PUT', $prefix . '/{namespace}/{resource}/{key}'],
            ['PATCH', $prefix . '/{namespace}/{resource}/{key}'],
            ['DELETE', $prefix . '/{namespace}/{resource}/{key}'],
        ];

        foreach ($routes as [$method, $path]) {
            $registry->addRoute($method, $path, $action, RouteRegistryInterface::PRIORITY_MODULE);
        }
    }

    private function tablePrefix(Application $app): string
    {
        $prefix = $app->config('headless-cms.table_prefix');
        if (is_string($prefix) && $prefix !== '') {
            return $prefix;
        }

        $fallback = $app->config('ecommerce.table_prefix', 'pw_');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'pw_';
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $abstract
     *
     * @return T
     */
    private function resolve(Application $app, string $abstract): object
    {
        $instance = $app->make($abstract);
        if (!$instance instanceof $abstract) {
            throw new \RuntimeException(sprintf('%s is not bound in the container.', $abstract));
        }

        return $instance;
    }
}