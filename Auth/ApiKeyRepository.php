<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Auth;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ApiKeyRepositoryInterface;

final class ApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function __construct(
        private DatabaseInterface $db,
        private string $prefix,
    ) {
    }

    public function findByPrefix(string $prefix): ?array
    {
        $row = $this->db->select('*')
            ->from($this->table())
            ->where('key_prefix', $prefix)
            ->run()
            ->fetch();

        if (!is_array($row)) {
            return null;
        }

        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    private function table(): string
    {
        return $this->prefix . 'headless_api_keys';
    }
}