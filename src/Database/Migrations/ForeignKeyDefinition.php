<?php

namespace Vireo\Framework\Database\Migrations;

/**
 * Foreign Key Definition
 *
 * Fluent builder for defining foreign key constraints
 */
class ForeignKeyDefinition
{
    /**
     * The blueprint instance
     */
    protected Blueprint $blueprint;

    /**
     * Local column names
     */
    public array $columns;

    /**
     * Referenced table
     */
    public ?string $referencedTable = null;

    /**
     * Referenced columns
     */
    public array $referencedColumns = [];

    /**
     * On delete action
     */
    public ?string $onDelete = null;

    /**
     * On update action
     */
    public ?string $onUpdate = null;

    /**
     * Constraint name
     */
    public ?string $name = null;

    /**
     * Create a new foreign key definition
     */
    public function __construct(Blueprint $blueprint, array $columns)
    {
        $this->blueprint = $blueprint;
        $this->columns = $columns;
    }

    /**
     * Set the referenced table and columns
     */
    public function references(string|array $columns): static
    {
        $this->referencedColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * Set the referenced table
     */
    public function on(string $table): static
    {
        $this->referencedTable = $table;
        return $this;
    }

    /**
     * Set the on delete action
     */
    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    /**
     * Set the on update action
     */
    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    /**
     * Set cascade on delete
     */
    public function cascadeOnDelete(): static
    {
        return $this->onDelete('CASCADE');
    }

    /**
     * Set cascade on update
     */
    public function cascadeOnUpdate(): static
    {
        return $this->onUpdate('CASCADE');
    }

    /**
     * Set restrict on delete
     */
    public function restrictOnDelete(): static
    {
        return $this->onDelete('RESTRICT');
    }

    /**
     * Set restrict on update
     */
    public function restrictOnUpdate(): static
    {
        return $this->onUpdate('RESTRICT');
    }

    /**
     * Set null on delete
     */
    public function nullOnDelete(): static
    {
        return $this->onDelete('SET NULL');
    }

    /**
     * Set no action on delete
     */
    public function noActionOnDelete(): static
    {
        return $this->onDelete('NO ACTION');
    }

    /**
     * Set the constraint name
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Finalize the foreign key definition
     */
    public function __destruct()
    {
        // This is called when the foreign key definition goes out of scope
        // The blueprint will handle adding it to the commands
    }
}
