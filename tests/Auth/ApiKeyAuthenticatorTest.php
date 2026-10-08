<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Tests\Auth;

use PrestoWorld\Modules\HeadlessCMS\Auth\ApiKeyAuthenticator;
use PrestoWorld\Modules\HeadlessCMS\Http\ErrorCode;
use PrestoWorld\Modules\HeadlessCMS\Tests\Support\InMemoryApiKeyRepository;
use PrestoWorld\Modules\HeadlessCMS\Tests\TestCase;

final class ApiKeyAuthenticatorTest extends TestCase
{
    private const SECRET = 'ABCDEF12secret-xyz';

    public function test_missing_key_is_unauthorized(): void
    {
        $result = $this->authenticator()->authenticate(null, 'shop:products:write');

        self::assertFalse($result->ok);
        self::assertSame(401, $result->status);
        self::assertSame(ErrorCode::UNAUTHORIZED, $result->code);
    }

    public function test_unknown_prefix_is_unauthorized(): void
    {
        $result = $this->authenticator()->authenticate('ZZZZZZZZnope', 'shop:products:write');

        self::assertFalse($result->ok);
        self::assertSame(ErrorCode::UNAUTHORIZED, $result->code);
    }

    public function test_bad_hash_is_unauthorized(): void
    {
        $repo = new InMemoryApiKeyRepository();
        $repo->add(self::SECRET, ['key_hash' => hash('sha256', 'different')]);
        $result = (new ApiKeyAuthenticator($repo))->authenticate(self::SECRET, 'shop:products:write');

        self::assertFalse($result->ok);
        self::assertSame(ErrorCode::UNAUTHORIZED, $result->code);
    }

    public function test_disabled_key_is_forbidden(): void
    {
        $result = $this->authenticator(['status' => 0])->authenticate(self::SECRET, 'shop:products:write');

        self::assertFalse($result->ok);
        self::assertSame(403, $result->status);
        self::assertSame(ErrorCode::FORBIDDEN, $result->code);
    }

    public function test_expired_key_is_forbidden(): void
    {
        $result = $this->authenticator(['expires_at' => time() - 60])
            ->authenticate(self::SECRET, 'shop:products:write');

        self::assertFalse($result->ok);
        self::assertSame(ErrorCode::FORBIDDEN, $result->code);
    }

    public function test_missing_scope_is_forbidden(): void
    {
        $result = $this->authenticator(['scopes' => ['shop:orders:write']])
            ->authenticate(self::SECRET, 'shop:products:write');

        self::assertFalse($result->ok);
        self::assertSame(ErrorCode::FORBIDDEN, $result->code);
    }

    public function test_exact_scope_allows(): void
    {
        $result = $this->authenticator(['scopes' => ['shop:products:write']])
            ->authenticate(self::SECRET, 'shop:products:write');

        self::assertTrue($result->ok);
        self::assertIsArray($result->key);
    }

    public function test_wildcard_scope_allows(): void
    {
        $result = $this->authenticator(['scopes' => ['*']])->authenticate(self::SECRET, 'anything:at:all');

        self::assertTrue($result->ok);
    }

    public function test_json_encoded_scopes_are_decoded(): void
    {
        $result = $this->authenticator(['scopes' => json_encode(['shop:products:write'])])
            ->authenticate(self::SECRET, 'shop:products:write');

        self::assertTrue($result->ok);
    }

    public function test_has_scope_directly(): void
    {
        $auth = new ApiKeyAuthenticator(new InMemoryApiKeyRepository());

        self::assertTrue($auth->hasScope(['scopes' => ['*']], 'x:y:z'));
        self::assertTrue($auth->hasScope(['scopes' => ['a:b:c']], 'a:b:c'));
        self::assertFalse($auth->hasScope(['scopes' => ['a:b:c']], 'x:y:z'));
        self::assertFalse($auth->hasScope([], 'x:y:z'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function authenticator(array $overrides = []): ApiKeyAuthenticator
    {
        $repo = new InMemoryApiKeyRepository();
        $repo->add(self::SECRET, $overrides);

        return new ApiKeyAuthenticator($repo);
    }
}