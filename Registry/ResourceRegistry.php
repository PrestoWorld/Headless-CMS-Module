<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Registry;

use PrestoWorld\Modules\HeadlessCMS\Contracts\ProviderInterface;

/**
 * Collects providers without eagerly loading them.
 *
 * Each provider is declared in its module manifest as a namespace => class
 * map, so the registry can resolve a single namespace on demand and never
 * instantiate (or autoload) providers that are not part of the current request:
 *
 *   "headless": { "providers": { "ecommerce": "Vendor\\...\\EcommerceProvider" } }
 */
final class ResourceRegistry
{
    /** @var array<string, ProviderInterface> already-instantiated providers */
    private array $providers = [];

    /** @var array<string, string> namespace => provider class */
    private array $classes = [];

    /** @var \Closure(string): (ProviderInterface|null) */
    private \Closure $resolver;

    /**
     * @param (\Closure(string): (ProviderInterface|null))|null $resolver
     */
    public function __construct(?\Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static fn (string $class): ?ProviderInterface => null;
    }

    public function register(ProviderInterface $provider): void
    {
        $this->providers[$provider->namespace()] = $provider;
    }

    public function registerClass(string $namespace, string $providerClass): void
    {
        $this->classes[$namespace] = $providerClass;
    }

    /**
     * Resolve a single namespace, instantiating only that provider.
     */
    public function get(string $namespace): ?ProviderInterface
    {
        if (isset($this->providers[$namespace])) {
            return $this->providers[$namespace];
        }

        if (!isset($this->classes[$namespace])) {
            return null;
        }

        $provider = ($this->resolver)($this->classes[$namespace]);

        if ($provider instanceof ProviderInterface) {
            $this->providers[$provider->namespace()] = $provider;
        }

        return $this->providers[$namespace] ?? null;
    }

    /**
     * Instantiate every known provider. Only used by the discovery endpoint,
     * never on a normal resource request.
     *
     * @return array<string, ProviderInterface>
     */
    public function all(): array
    {
        foreach (array_keys($this->classes) as $namespace) {
            $this->get($namespace);
        }

        return $this->providers;
    }

    /**
     * @return list<string>
     */
    public function namespaces(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->providers),
            array_keys($this->classes),
        )));
    }
}