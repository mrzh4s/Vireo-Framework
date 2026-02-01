<?php

namespace Framework\Database\Migrations\Grammars;

use Framework\Database\Migrations\Blueprint;
use Framework\Database\Migrations\ColumnDefinition;

/**
 * MySQL Schema Grammar
 *
 * Generates MySQL-specific DDL statements
 */
class MySQLSchemaGrammar extends SchemaGrammar
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
                $columns = array_map(fn($col) => $this->wrap($col), $command['columns']);
                $sql .= ', PRIMARY KEY (' . implode(', ', $columns) . ')';
            }
        }

        $sql .= ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return $sql;
    }

    /**
     * Compile alter table statements
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];

        // Add new columns
        foreach ($blueprint->getColumns() as $column) {
            $sql = "ALTER TABLE {$this->wrapTable($blueprint->table)} ADD COLUMN " .
                $this->compileColumnDefinition($column);

            if ($column->get('after')) {
                $sql .= ' AFTER ' . $this->wrap($column->get('after'));
            } elseif ($column->get('first')) {
                $sql .= ' FIRST';
            }

            $statements[] = $sql;
        }

        // Process commands
        foreach ($blueprint->getCommands() as $command) {
            $method = 'compile' . ucfirst($command['type']);
            if (method_exists($this, $method)) {
                $result = $this->$method($blueprint, $command);
                if (is_array($result)) {
                    $statements = array_merge($statements, $result);
                } else {
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
        return "RENAME TABLE {$this->wrapTable($from)} TO {$this->wrapTable($to)}";
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
        // Handle auto-increment specially
        if ($column->get('autoIncrement', false)) {
            return match ($column->type) {
                'tinyInteger' => 'TINYINT AUTO_INCREMENT',
                'smallInteger' => 'SMALLINT AUTO_INCREMENT',
                'integer' => 'INT AUTO_INCREMENT',
                'bigInteger' => 'BIGINT AUTO_INCREMENT',
                default => $this->getTypeWithoutIncrement($column),
            };
        }

        return $this->getTypeWithoutIncrement($column);
    }

    /**
     * Get type without auto-increment
     */
    protected function getTypeWithoutIncrement(ColumnDefinition $column): string
    {
        return match ($column->type) {
            // Integer types
            'tinyInteger' => 'TINYINT',
            'smallInteger' => 'SMALLINT',
            'integer' => 'INT',
            'bigInteger' => 'BIGINT',

            // String types
            'string' => 'VARCHAR(' . ($column->get('length', 255)) . ')',
            'char' => 'CHAR(' . ($column->get('length', 255)) . ')',
            'text' => 'TEXT',
            'mediumText' => 'MEDIUMTEXT',
            'longText' => 'LONGTEXT',

            // Numeric types
            'decimal' => 'DECIMAL(' . $column->get('precision', 8) . ',' . $column->get('scale', 2) . ')',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',

            // Boolean
            'boolean' => 'TINYINT(1)',

            // Date/Time types
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',

            // JSON
            'json' => 'JSON',
            'jsonb' => 'JSON',

            // Binary
            'binary' => 'BLOB',

            // UUID
            'uuid' => 'CHAR(36)',

            // Enum
            'enum' => $this->getEnumType($column),

            default => 'VARCHAR(255)',
        };
    }

    /**
     * Get enum type definition
     */
    protected function getEnumType(ColumnDefinition $column): string
    {
        $allowed = $column->get('allowed', []);
        $values = array_map(fn($v) => $this->quoteString($v), $allowed);
        return 'ENUM(' . implode(', ', $values) . ')';
    }

    /**
     * Get unsigned modifier
     */
    protected function modifyUnsigned(ColumnDefinition $column): ?string
    {
        if ($column->get('unsigned', false)) {
            return 'UNSIGNED';
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

        return "ALTER TABLE {$this->wrapTable($blueprint->table)} ADD INDEX {$this->wrap($name)} (" .
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

        return "ALTER TABLE {$this->wrapTable($blueprint->table)} ADD UNIQUE {$this->wrap($name)} (" .
            implode(', ', $columns) . ")";
    }

    /**
     * Compile a foreign key command
     */
    public function compileForeign(Blueprint $blueprint, array $command): string
    {
        $foreign = $command['definition'];
        $columns = array_map(fn($col) => $this->wrap($col), $foreign->columns);
        $refColumns = array_map(fn($col) => $this->wrap($col), $foreign->referencedColumns);

        $name = $foreign->name ?? $this->generateIndexName(
            $blueprint->table,
            $foreign->columns,
            'foreign'
        );

        $sql = "ALTER TABLE {$this->wrapTable($blueprint->table)} " .
            "ADD CONSTRAINT {$this->wrap($name)} " .
            "FOREIGN KEY (" . implode(', ', $columns) . ") " .
            "REFERENCES {$this->wrapTable($foreign->referencedTable)} (" . implode(', ', $refColumns) . ")";

        if ($foreign->onDelete) {
            $sql .= " ON DELETE {$foreign->onDelete}";
        }

        if ($foreign->onUpdate) {
            $sql .= " ON UPDATE {$foreign->onUpdate}";
        }

        return $sql;
    }

    /**
     * Compile drop column command
     */
    public function compileDropColumn(Blueprint $blueprint, array $command): array
    {
        $statements = [];
        foreach ($command['columns'] as $column) {
            $statements[] = "ALTER TABLE {$this->wrapTable($blueprint->table)} DROP COLUMN {$this->wrap($column)}";
        }
        return $statements;
    }

    /**
     * Compile rename column command
     */
    public function compileRenameColumn(Blueprint $blueprint, array $command): string
    {
        return "ALTER TABLE {$this->wrapTable($blueprint->table)} " .
            "RENAME COLUMN {$this->wrap($command['from'])} TO {$this->wrap($command['to'])}";
    }

    /**
     * Wrap a table in keyword identifiers
     */
    public function wrapTable(string $table): string
    {
        if (str_contains($table, '.')) {
            $parts = explode('.', $table);
            return $this->wrap($parts[0]) . '.' . $this->wrap($parts[1]);
        }

        return $this->wrap($table);
    }

    /**
     * Wrap a value in keyword identifiers (backticks for MySQL)
     */
    public function wrap(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }
}
