<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->schema($db, $prefix . 'headless_api_keys');
        $table->primary('id');
        $table->column('name')->string(255);
        $table->column('key_prefix')->string(16);
        $table->column('key_hash')->string(64);
        $table->column('scopes')->text()->nullable();
        $table->column('status')->integer()->defaultValue(1);
        $table->column('expires_at')->integer()->nullable();
        $table->column('last_used_at')->integer()->nullable();
        $table->column('created_at')->integer();
        $table->index(['key_prefix'])->unique();
        $table->save();
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'headless_api_keys';

        if ($db->hasTable($name)) {
            $schema = $this->schema($db, $name);
            $schema->declareDropped();
            $schema->save();
        }
    }

    /**
     * DatabaseInterface only promises TableInterface, but schema editing lives on the
     * concrete Cycle Table — fail loudly if the runtime instance is anything else.
     *
     * @param non-empty-string $name
     */
    private function schema(DatabaseInterface $db, string $name): AbstractTable
    {
        $table = $db->table($name);

        if (!$table instanceof \Cycle\Database\Table) {
            throw new \RuntimeException("Expected concrete Cycle table for {$name}, got " . $table::class);
        }

        return $table->getSchema();
    }
};