<?php

namespace Vireo\Framework\Database\ORM\Grammar;

/**
 * SQLite Grammar
 *
 * Generates SQLite-specific SQL syntax
 */
class SQLiteGrammar extends Grammar
{
    /**
     * SQLite-supported operators
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'LIKE', 'NOT LIKE',
        'GLOB', 'NOT GLOB',
        'MATCH', 'NOT MATCH',
        'REGEXP', 'NOT REGEXP',
    ];

    /**
     * Compile a select query
     */
    public function compileSelect(array $components): string
    {
        $sql = ['SELECT'];

        // Add select columns
        $sql[] = $this->compileColumns($components['columns'] ?? ['*']);

        // Add select raw expressions
        if (!empty($components['selectRaw'])) {
            $sql[] = ', ' . implode(', ', $components['selectRaw']);
        }

        // Add from clause
        if (isset($components['from'])) {
            $sql[] = $this->compileFrom($components['from']);
        }

        // Add joins
        if (!empty($components['joins'])) {
            $sql[] = $this->compileJoins($components['joins']);
        }

        // Add where clauses
        if (!empty($components['wheres'])) {
            $sql[] = $this->compileWheres($components['wheres']);
        }

        // Add group by
        if (!empty($components['groups'])) {
            $sql[] = $this->compileGroups($components['groups']);
        }

        // Add having
        if (!empty($components['havings'])) {
            $sql[] = $this->compileHavings($components['havings']);
        }

        // Add order by
        if (!empty($components['orders'])) {
            $sql[] = $this->compileOrders($components['orders']);
        }

        // Add limit
        if (isset($components['limit'])) {
            $sql[] = $this->compileLimit($components['limit']);
        }

        // Add offset
        if (isset($components['offset'])) {
            $sql[] = $this->compileOffset($components['offset']);
        }

        return implode(' ', $sql);
    }

    /**
     * Compile an insert query
     */
    public function compileInsert(string $table, array $values): string
    {
        if (empty($values)) {
            return "INSERT INTO {$this->wrapTable($table)} DEFAULT VALUES";
        }

        $columns = array_keys($values[0]);
        $wrappedColumns = array_map(fn($col) => $this->wrap($col), $columns);

        $placeholders = [];
        foreach ($values as $row) {
            $rowPlaceholders = str_repeat('?, ', count($row) - 1) . '?';
            $placeholders[] = "({$rowPlaceholders})";
        }

        $columnList = implode(', ', $wrappedColumns);
        $valuesList = implode(', ', $placeholders);

        return "INSERT INTO {$this->wrapTable($table)} ({$columnList}) VALUES {$valuesList}";
    }

    /**
     * Compile an insert and return ID query
     * Note: SQLite uses PDO::lastInsertId() which is handled by QueryBuilder
     */
    public function compileInsertGetId(string $table, array $values, string $sequence = 'id'): string
    {
        return $this->compileInsert($table, [$values]);
    }

    /**
     * Compile an update query
     */
    public function compileUpdate(string $table, array $values, array $wheres): string
    {
        $columns = [];
        foreach (array_keys($values) as $column) {
            $columns[] = $this->wrap($column) . ' = ?';
        }

        $sql = "UPDATE {$this->wrapTable($table)} SET " . implode(', ', $columns);

        if (!empty($wheres)) {
            $sql .= ' ' . $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * Compile a delete query
     */
    public function compileDelete(string $table, array $wheres): string
    {
        $sql = "DELETE FROM {$this->wrapTable($table)}";

        if (!empty($wheres)) {
            $sql .= ' ' . $this->compileWheres($wheres);
        }

        return $sql;
    }

    /**
     * Compile a truncate query (SQLite doesn't have TRUNCATE, use DELETE)
     */
    public function compileTruncate(string $table): string
    {
        return "DELETE FROM {$this->wrapTable($table)}";
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
        if ($value === '*' || str_contains($value, '"')) {
            return $value;
        }

        if (str_contains($value, '(')) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Get the format for database stored dates
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Determine if the grammar supports savepoints
     */
    public function supportsSavepoints(): bool
    {
        return true;
    }
}
