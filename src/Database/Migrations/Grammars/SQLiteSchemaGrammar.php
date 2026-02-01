<?php

namespace Framework\Database\Migrations\Grammars;

use Framework\Database\Migrations\Blueprint;
use Framework\Database\Migrations\ColumnDefinition;

/**
 * SQLite Schema Grammar
 *
 * Generates SQLite-specific DDL statements
 * Note: SQLite has limitations on ALTER TABLE
 */
class SQLiteSchemaGrammar extends SchemaGrammar
{
    /**
     * Compile a create table statement
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        $sql = "CREATE TABLE {$this->wrapTable($blueprint->table)} (";

        $columns = [];
        foreach ($blueprint->getColumns() as $column) {
            $columns[] = $this->compileColumnDefinition($column);
        }

        $sql .= implode(', ', $columns);

        // Add primary key if not already set in columns
        foreach ($blueprint->getCommands() as $command) {
            if ($command['type'] === 'primary') {
                $cols = array_map(fn($col) => $this->wrap($col), $command['columns']);
                $sql .= ', PRIMARY KEY (' . implode(', ', $cols) . ')';
            }
        }

        $sql .= ')';

        return $sql;
    }

    /**
     * Compile alter table statements
     * Note: SQLite has very limited ALTER TABLE support
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];

        // Add new columns (only supported alter in SQLite < 3.25)
        foreach ($blueprint->getColumns() as $column) {
            $statements[] = "ALTER TABLE {$this->wrapTable($blueprint->table)} ADD COLUMN " .
                $this->compileColumnDefinition($column);
        }

        // Process other commands
        foreach ($blueprint->getCommands() as $command) {
            $method = 'compile' . ucfirst($command['type']);
            if (method_exists($this, $method)) {
                $result = $this->$method($blueprint, $command);
                if (is_array($result)) {
                    $statements = array_merge($statements, $result);
                } elseif ($result) {
                    $statements[] = $result;
                }
            }
        }

        return $statements;
    }

    /**
     * Compile a drop table statement
     */
    public function compileDrop(string $table): string
    {
        return "DROP TABLE {$this->wrapTable($table)}";
    }

    /**
     * Compile a drop table if exists statement
     */
    public function compileDropIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS {$this->wrapTable($table)}";
    }

    /**
     * Compile a rename table statement
     */
    public function compileRename(string $from, string $to): string
    {
        return "ALTER TABLE {$this->wrapTable($from)} RENAME TO {$this->wrapTable($to)}";
    }

    /**
     * Compile a column definition
     */
    protected function compileColumnDefinition(ColumnDefinition $column): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->getType($column);
        $sql .= $this->getModifiers($column);

        return $sql;
    }

    /**
     * Get the SQL type for a column
     */
    protected function getType(ColumnDefinition $column): string
    {
        return match ($column->type) {
            // Integer types
            'tinyInteger', 'smallInteger', 'integer' => 'INTEGER',
            'bigInteger' => 'INTEGER',

            // String types
            'string', 'char' => 'TEXT',
            'text', 'mediumText', 'longText' => 'TEXT',

            // Numeric types
            'decimal', 'float', 'double' => 'REAL',

            // Boolean
            'boolean' => 'INTEGER',

            // Date/Time types
            'date', 'datetime', 'timestamp' => 'TEXT',
            'time' => 'TEXT',

            // JSON (stored as TEXT in SQLite)
            'json', 'jsonb' => 'TEXT',

            // Binary
            'binary' => 'BLOB',

            // UUID
            'uuid' => 'TEXT',

            // Enum (stored as TEXT with CHECK constraint)
            'enum' => $this->getEnumType($column),

            default => 'TEXT',
        };
    }

    /**
     * Get enum type definition (TEXT with CHECK constraint)
     */
    protected function getEnumType(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    /**
     * Get increment modifier (SQLite uses INTEGER PRIMARY KEY for autoincrement)
     */
    protected function modifyIncrement(ColumnDefinition $column): ?string
    {
        if ($column->get('autoIncrement', false)) {
            return 'PRIMARY KEY AUTOINCREMENT';
        }

        return null;
    }

    /**
     * Compile an index command
     */
    public function compileIndex(Blueprint $blueprint, array $command): string
    {
        $index = $command['definition'];
        $columns = array_map(fn($col) => $this->wrap($col), $index->columns);
        $name = $index->name ?? $this->generateIndexName($blueprint->table, $index->columns, 'index');

        return "CREATE INDEX {$this->wrap($name)} ON {$this->wrapTable($blueprint->table)} (" .
            implode(', ', $columns) . ")";
    }

    /**
     * Compile a unique index command
     */
    public function compileUnique(Blueprint $blueprint, array $command): string
    {
        $index = $command['definition'];
        $columns = array_map(fn($col) => $this->wrap($col), $index->columns);
        $name = $index->name ?? $this->generateIndexName($blueprint->table, $index->columns, 'unique');

        return "CREATE UNIQUE INDEX {$this->wrap($name)} ON {$this->wrapTable($blueprint->table)} (" .
            implode(', ', $columns) . ")";
    }

    /**
     * Compile a foreign key command
     * Note: SQLite foreign keys must be defined in CREATE TABLE, not ALTER TABLE
     * This will return null as SQLite doesn't support ADD CONSTRAINT
     */
    public function compileForeign(Blueprint $blueprint, array $command): ?string
    {
        // SQLite doesn't support adding foreign keys via ALTER TABLE
        // They must be defined in the CREATE TABLE statement
        return null;
    }

    /**
     * Compile drop column command
     * Note: SQLite doesn't support DROP COLUMN (before version 3.35.0)
     */
    public function compileDropColumn(Blueprint $blueprint, array $command): array
    {
        // SQLite < 3.35.0 doesn't support DROP COLUMN
        // Would need to recreate the table
        // For now, return empty array (no operation)
        return [];
    }

    /**
     * Compile rename column command
     * Note: SQLite has limited support for RENAME COLUMN
     */
    public function compileRenameColumn(Blueprint $blueprint, array $command): string
    {
        // SQLite 3.25.0+ supports RENAME COLUMN
        return "ALTER TABLE {$this->wrapTable($blueprint->table)} " .
            "RENAME COLUMN {$this->wrap($command['from'])} TO {$this->wrap($command['to'])}";
    }

    /**
     * Wrap a table in keyword identifiers
     */
    public function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    /**
     * Wrap a value in keyword identifiers (double quotes for SQLite)
     */
    public function wrap(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Override modifier order for SQLite
     */
    protected function getModifierOrder(): array
    {
        return [
            'Increment',  // Must come before nullable/default
            'Nullable',
            'Default',
        ];
    }
}
