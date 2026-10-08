<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\HeadlessCMS\Console;

use Cycle\Database\DatabaseInterface;
use Witals\Framework\Console\Command;

/**
 * Manage headless API keys.
 *
 *   php witals headless:key create --name="Blogger" --scope="*"
 *   php witals headless:key list
 *   php witals headless:key revoke <id>
 */
final class ApiKeyCommand extends Command
{
    protected string $name = 'headless:key';

    protected string $description = 'Manage headless API keys: create | list | revoke';

    /** @var array<string, string> */
    protected array $options = [
        '--name=NAME' => 'Label for the created key',
        '--scope=SCOPES' => 'Comma-separated scopes, e.g. "ecommerce:orders:write" or "*"',
        '--expires=TIMESTAMP' => 'Optional unix timestamp expiry',
    ];

    /**
     * @param list<string> $args
     */
    public function handle(array $args): int
    {
        $db = $this->app->make(DatabaseInterface::class);
        if (!$db instanceof DatabaseInterface) {
            $this->error('DatabaseInterface is not bound in the container.');

            return 1;
        }

        $positional = array_values(array_filter($args, static fn (string $arg): bool => !str_starts_with($arg, '-')));
        $action = $positional[0] ?? 'list';

        return match ($action) {
            'create' => $this->create($db, $args, $positional),
            'list' => $this->list($db),
            'revoke' => $this->revoke($db, $positional),
            default => $this->unknown($action),
        };
    }

    /**
     * @param list<string> $args
     * @param list<string> $positional
     */
    private function create(DatabaseInterface $db, array $args, array $positional): int
    {
        $name = $this->stringOption($args, 'name') ?? ($positional[1] ?? 'API Key');
        $scopeRaw = $this->stringOption($args, 'scope') ?? '*';
        $scopes = array_values(array_filter(array_map('trim', explode(',', $scopeRaw)), static fn (string $s): bool => $s !== ''));

        $secret = 'hls_' . bin2hex(random_bytes(24));
        $expires = $this->stringOption($args, 'expires');

        $db->insert($this->table())
            ->values([
                'name' => $name,
                'key_prefix' => substr($secret, 0, 8),
                'key_hash' => hash('sha256', $secret),
                'scopes' => json_encode($scopes, JSON_UNESCAPED_SLASHES) ?: '[]',
                'status' => 1,
                'expires_at' => is_numeric($expires) ? (int) $expires : null,
                'created_at' => time(),
            ])
            ->run();

        $this->info('API key created. Store it now — it will not be shown again:');
        $this->line($secret);

        return 0;
    }

    private function list(DatabaseInterface $db): int
    {
        $rows = $db->select('*')->from($this->table())->orderBy('id', 'ASC')->fetchAll();

        if ($rows === []) {
            $this->comment('No API keys found.');

            return 0;
        }

        foreach ($rows as $row) {
            $this->line(sprintf(
                '#%s  %-20s prefix=%s  status=%s  scopes=%s',
                (string) ($row['id'] ?? '?'),
                (string) ($row['name'] ?? ''),
                (string) ($row['key_prefix'] ?? ''),
                (string) ($row['status'] ?? '0'),
                (string) ($row['scopes'] ?? ''),
            ));
        }

        return 0;
    }

    /**
     * @param list<string> $positional
     */
    private function revoke(DatabaseInterface $db, array $positional): int
    {
        $id = $positional[1] ?? '';
        if (!is_numeric($id)) {
            $this->error('Usage: headless:key revoke <id>');

            return 1;
        }

        $db->update($this->table(), ['status' => 0], ['id' => (int) $id])->run();
        $this->info('API key #' . (int) $id . ' revoked.');

        return 0;
    }

    private function unknown(string $action): int
    {
        $this->error('Unknown action: ' . $action);
        $this->comment('Available actions: create, list, revoke');

        return 1;
    }

    /**
     * @param list<string> $args
     */
    private function stringOption(array $args, string $name): ?string
    {
        $value = $this->getOption($args, $name);

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function table(): string
    {
        $prefix = $this->app->config('headless-cms.table_prefix');
        if (!is_string($prefix) || $prefix === '') {
            $fallback = $this->app->config('ecommerce.table_prefix', 'pw_');
            $prefix = is_string($fallback) && $fallback !== '' ? $fallback : 'pw_';
        }

        return $prefix . 'headless_api_keys';
    }
}