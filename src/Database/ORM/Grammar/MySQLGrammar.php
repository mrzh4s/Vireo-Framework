<?php

namespace Vireo\Framework\Database\ORM\Grammar;

/**
 * MySQL Grammar
 *
 * Generates MySQL-specific SQL syntax
 */
class MySQLGrammar extends Grammar
{
    /**
     * MySQL-supported operators
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'LIKE', 'NOT LIKE',
        'REGEXP', 'NOT REGEXP',
        'RLIKE', 'NOT RLIKE',
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
            return "INSERT INTO {$this->wrapTable($table)} () VALUES ()";
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
     * Note: MySQL uses LAST_INSERT_ID() which is handled by PDO::lastInsertId()
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
     * Compile a truncate query
     */
    public function compileTruncate(string $table): string
    {
        return "TRUNCATE TABLE {$this->wrapTable($table)}";
    }

    /**
     * Wrap a table in keyword identifiers
     */
    public function wrapTable(string $table): string
    {
        // Handle database.table notation
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
        if ($value === '*' || str_contains($value, '`')) {
            return $value;
        }

        if (str_contains($value, '(')) {
            return $value;
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * Get the format for database stored dates
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }
}
