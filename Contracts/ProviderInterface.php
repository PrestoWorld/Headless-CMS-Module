<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Contracts;

use PrestoWorld\Modules\HeadlessCMS\Resource\ResourceDefinition;

/**
 * A headless provider exposes one or more resources under a single namespace,
 * e.g. the "ecommerce" namespace exposing products, orders and customers.
 *
 * Any module can publish its own provider by declaring it in its manifest:
 *
 *   "headless": { "providers": ["Vendor\\Module\\MyProvider"] }
 */
interface ProviderInterface
{
    /**
     * Namespace segment used in the URL: /api/headless/{namespace}/{resource}.
     */
    public function namespace(): string;

    /**
     * @return array<string, ResourceDefinition> keyed by resource name
     */
    public function resources(): array;
}