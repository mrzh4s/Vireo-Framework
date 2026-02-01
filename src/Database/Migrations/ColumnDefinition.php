<?php

namespace Framework\Database\Migrations;

/**
 * Column Definition
 *
 * Fluent builder for defining table columns with modifiers
 */
class ColumnDefinition
{
    /**
     * Column type
     */
    public string $type;

    /**
     * Column name
     */
    public string $name;

    /**
     * Column attributes
     */
    public array $attributes = [];

    /**
     * Create a new column definition
     */
    public function __construct(string $type, string $name, array $attributes = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->attributes = $attributes;
    }

    /**
     * Make the column nullable
     */
    public function nullable(bool $value = true): static
    {
        $this->attributes['nullable'] = $value;
        return $this;
    }

    /**
     * Set a default value
     */
    public function default(mixed $value): static
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    /**
     * Make the column unsigned (numeric types)
     */
    public function unsigned(): static
    {
        $this->attributes['unsigned'] = true;
        return $this;
    }

    /**
     * Make the column auto-incrementing
     */
    public function autoIncrement(): static
    {
        $this->attributes['autoIncrement'] = true;
        return $this;
    }

    /**
     * Set the column as primary key
     */
    public function primary(): static
    {
        $this->attributes['primary'] = true;
        return $this;
    }

    /**
     * Set the column as unique
     */
    public function unique(?string $indexName = null): static
    {
        $this->attributes['unique'] = true;
        $this->attributes['uniqueIndexName'] = $indexName;
        return $this;
    }

    /**
     * Add an index on this column
     */
    public function index(?string $indexName = null): static
    {
        $this->attributes['index'] = true;
        $this->attributes['indexName'] = $indexName;
        return $this;
    }

    /**
     * Add a comment to the column
     */
    public function comment(string $comment): static
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    /**
     * Set the character set (MySQL)
     */
    public function charset(string $charset): static
    {
        $this->attributes['charset'] = $charset;
        return $this;
    }

    /**
     * Set the collation
     */
    public function collation(string $collation): static
    {
        $this->attributes['collation'] = $collation;
        return $this;
    }

    /**
     * Mark column for update (used in alter table)
     */
    public function change(): static
    {
        $this->attributes['change'] = true;
        return $this;
    }

    /**
     * Place column after another column (MySQL)
     */
    public function after(string $column): static
    {
        $this->attributes['after'] = $column;
        return $this;
    }

    /**
     * Place column first (MySQL)
     */
    public function first(): static
    {
        $this->attributes['first'] = true;
        return $this;
    }

    /**
     * Set SRID for spatial columns (PostGIS)
     */
    public function srid(int $srid): static
    {
        $this->attributes['srid'] = $srid;
        return $this;
    }

    /**
     * Set spatial type for geography/geometry columns (PostGIS)
     */
    public function spatialType(string $type): static
    {
        $this->attributes['spatialType'] = $type;
        return $this;
    }

    /**
     * Set check constraint
     */
    public function check(string $constraint): static
    {
        $this->attributes['check'] = $constraint;
        return $this;
    }

    /**
     * Set the storage engine (MySQL)
     */
    public function storedAs(string $expression): static
    {
        $this->attributes['storedAs'] = $expression;
        return $this;
    }

    /**
     * Set as virtual generated column
     */
    public function virtualAs(string $expression): static
    {
        $this->attributes['virtualAs'] = $expression;
        return $this;
    }

    /**
     * Get an attribute value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if attribute exists
     */
    public function has(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}
