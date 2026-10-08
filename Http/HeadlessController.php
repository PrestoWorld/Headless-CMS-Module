<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Http;

use Witals\Framework\Http\Request;
use Witals\Framework\Http\Response;

/**
 * Single action target for every headless route. Registered as an array
 * callable ([HeadlessController::class, 'handle']) so route caching works.
 */
final class HeadlessController
{
    public function __construct(
        private Gateway $gateway,
    ) {
    }

    public function handle(
        Request $request,
        ?string $namespace = null,
        ?string $resource = null,
        ?string $key = null,
    ): Response {
        $apiKey = $request->header($this->gateway->apiKeyHeader());
        $apiKey = is_string($apiKey) ? $apiKey : null;

        return $this->gateway->handle(
            RequestContext::fromRequest($request, $namespace, $resource, $key, $apiKey),
        );
    }
}