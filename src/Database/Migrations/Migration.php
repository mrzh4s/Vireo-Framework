<?php

namespace Framework\Database\Migrations;

/**
 * Migration Base Class
 *
 * Base class for all PHP class-based migrations
 * Migrations extend this class and implement up() and down() methods
 */
abstract class Migration
{
    /**
     * Connection name for this migration
     */
    protected ?string $connection = null;

    /**
     * Run the migration (apply changes)
     */
    abstract public function up(): void;

    /**
     * Reverse the migration (rollback changes)
     *
     * This is optional - not all migrations need to be reversible
     */
    public function down(): void
    {
        // Optional - override in migration class if needed
    }

    /**
     * Get the Schema instance
     */
    protected function schema(): Schema
    {
        if ($this->connection !== null) {
            return Schema::connection($this->connection);
        }

        return new Schema();
    }

    /**
     * Get the connection name
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }
}
