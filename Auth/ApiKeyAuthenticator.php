<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Auth;

use PrestoWorld\Modules\HeadlessCMS\Contracts\ApiKeyRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Http\ErrorCode;

/**
 * Validates API keys presented via the configured header.
 *
 * Keys are stored as a sha-256 hash; only a short public prefix is stored in
 * clear text so lookup stays indexed while the secret can never be recovered.
 */
final class ApiKeyAuthenticator
{
    public function __construct(
        private ApiKeyRepositoryInterface $keys,
    ) {
    }

    public function authenticate(?string $presented, ?string $requiredScope): AuthResult
    {
        if ($presented === null || trim($presented) === '') {
            return AuthResult::deny(401, ErrorCode::UNAUTHORIZED, 'Missing API key.');
        }

        $presented = trim($presented);
        $prefix = substr($presented, 0, 8);
        $row = $this->keys->findByPrefix($prefix);

        if ($row === null) {
            return AuthResult::deny(401, ErrorCode::UNAUTHORIZED, 'Invalid API key.');
        }

        $hash = (string) ($row['key_hash'] ?? '');
        if ($hash === '' || !hash_equals($hash, hash('sha256', $presented))) {
            return AuthResult::deny(401, ErrorCode::UNAUTHORIZED, 'Invalid API key.');
        }

        if ((int) ($row['status'] ?? 0) !== 1) {
            return AuthResult::deny(403, ErrorCode::FORBIDDEN, 'API key is disabled.');
        }

        $expiresAt = (int) ($row['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            return AuthResult::deny(403, ErrorCode::FORBIDDEN, 'API key has expired.');
        }

        if ($requiredScope !== null && !$this->hasScope($row, $requiredScope)) {
            return AuthResult::deny(403, ErrorCode::FORBIDDEN, 'API key is missing scope: ' . $requiredScope);
        }

        return AuthResult::allow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function hasScope(array $row, string $required): bool
    {
        $scopes = $row['scopes'] ?? [];

        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($scopes)) {
            return false;
        }

        foreach ($scopes as $scope) {
            if (is_string($scope) && ($scope === '*' || $scope === $required)) {
                return true;
            }
        }

        return false;
    }
}