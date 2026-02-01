<?php

namespace Vireo\Framework\Database\Migrations\Grammars;

use Vireo\Framework\Database\Migrations\Blueprint;
use Vireo\Framework\Database\Migrations\ColumnDefinition;

/**
 * Schema Grammar
 *
 * Abstract base class for database-specific schema generation
 */
abstract class SchemaGrammar
{
    /**
     * Compile a create table statement
     */
    abstract public function compileCreate(Blueprint $blueprint): string;

    /**
     * Compile alter table statements
     */
    abstract public function compileAlter(Blueprint $blueprint): array;

    /**
     * Compile a drop table statement
     */
    abstract public function compileDrop(string $table): string;

    /**
     * Compile a drop table if exists statement
     */
    abstract public function compileDropIfExists(string $table): string;

    /**
     * Compile a rename table statement
     */
    abstract public function compileRename(string $from, string $to): string;

    /**
     * Compile a column definition
     */
    abstract protected function compileColumnDefinition(ColumnDefinition $column): string;

    /**
     * Wrap a table in keyword identifiers
     */
    abstract public function wrapTable(string $table): string;

    /**
     * Wrap a value in keyword identifiers
     */
    abstract public function wrap(string $value): string;

    /**
     * Get the SQL for a column modifier
     */
    protected function getModifiers(ColumnDefinition $column): string
    {
        $modifiers = [];

        foreach ($this->getModifierOrder() as $modifier) {
            $method = "modify{$modifier}";
            if (method_exists($this, $method)) {
                $result = $this->$method($column);
                if ($result !== null) {
                    $modifiers[] = $result;
                }
            }
        }

        return implode(' ', $modifiers);
    }

    /**
     * Get the order of column modifiers
     */
    protected function getModifierOrder(): array
    {
        return [
            'Unsigned',
            'Nullable',
            'Default',
            'Increment',
            'Primary',
            'Unique',
            'Comment',
        ];
    }

    /**
     * Get the nullable modifier
     */
    protected function modifyNullable(ColumnDefinition $column): ?string
    {
        if ($column->get('nullable', false)) {
            return 'NULL';
        }

        return 'NOT NULL';
    }

    /**
     * Get the default modifier
     */
    protected function modifyDefault(ColumnDefinition $column): ?string
    {
        if ($column->has('default')) {
            $default = $column->get('default');

            if ($default === null) {
                return 'DEFAULT NULL';
            }

            if (is_bool($default)) {
                return 'DEFAULT ' . ($default ? 'TRUE' : 'FALSE');
            }

            if (is_numeric($default)) {
                return "DEFAULT {$default}";
            }

            return "DEFAULT '{$default}'";
        }

        return null;
    }

    /**
     * Format a value for inclusion in SQL
     */
    protected function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Generate index name
     */
    protected function generateIndexName(string $table, array $columns, string $type): string
    {
        $index = strtolower($table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $index);
    }
}
