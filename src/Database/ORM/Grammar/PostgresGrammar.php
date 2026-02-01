<?php

namespace Framework\Database\ORM\Grammar;

/**
 * PostgreSQL Grammar
 *
 * Generates PostgreSQL-specific SQL syntax including PostGIS spatial functions
 */
class PostgresGrammar extends Grammar
{
    /**
     * PostgreSQL-supported operators
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE',
        '~', '~*', '!~', '!~*', // Regex operators
        '@>', '<@', '&&', '||', // Array/JSON operators
        '->', '->>', '#>', '#>>', // JSON operators
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

        // Get column names from first row
        $columns = array_keys($values[0]);
        $wrappedColumns = array_map(fn($col) => $this->wrap($col), $columns);

        // Build placeholders for each row
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
     */
    public function compileInsertGetId(string $table, array $values, string $sequence = 'id'): string
    {
        return $this->compileInsert($table, [$values]) . " RETURNING {$this->wrap($sequence)}";
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
        return "TRUNCATE TABLE {$this->wrapTable($table)} RESTART IDENTITY CASCADE";
    }

    /**
     * Wrap a table in keyword identifiers
     */
    public function wrapTable(string $table): string
    {
        // Handle schema.table notation
        if (str_contains($table, '.')) {
            $parts = explode('.', $table);
            return $this->wrap($parts[0]) . '.' . $this->wrap($parts[1]);
        }

        return $this->wrap($table);
    }

    /**
     * Wrap a value in keyword identifiers
     */
    public function wrap(string $value): string
    {
        // Don't wrap * or already wrapped values
        if ($value === '*' || str_contains($value, '"')) {
            return $value;
        }

        // Don't wrap functions
        if (str_contains($value, '(')) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Compile a "where exists" clause
     */
    public function compileWhereExists(string $subquery): string
    {
        return "EXISTS ({$subquery})";
    }

    /**
     * Compile a "where not exists" clause
     */
    public function compileWhereNotExists(string $subquery): string
    {
        return "NOT EXISTS ({$subquery})";
    }

    /**
     * Get the format for database stored dates
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * Compile a PostGIS distance calculation
     */
    public function compileSpatialDistance(string $column, string $geometry, string $as): string
    {
        return "ST_Distance({$this->wrap($column)}, ST_GeomFromText(?, 4326)) AS {$this->wrap($as)}";
    }

    /**
     * Compile a PostGIS within/DWithin clause
     */
    public function compileSpatialWithin(string $column, string $geometry, float $distance): string
    {
        return "ST_DWithin({$this->wrap($column)}, ST_GeomFromText(?, 4326), ?)";
    }

    /**
     * Compile a PostGIS intersects clause
     */
    public function compileSpatialIntersects(string $column, string $geometry): string
    {
        return "ST_Intersects({$this->wrap($column)}, ST_GeomFromText(?, 4326))";
    }

    /**
     * Compile a PostGIS contains clause
     */
    public function compileSpatialContains(string $column, string $geometry): string
    {
        return "ST_Contains({$this->wrap($column)}, ST_GeomFromText(?, 4326))";
    }

    /**
     * Compile a PostGIS crosses clause
     */
    public function compileSpatialCrosses(string $column, string $geometry): string
    {
        return "ST_Crosses({$this->wrap($column)}, ST_GeomFromText(?, 4326))";
    }

    /**
     * Compile a PostGIS touches clause
     */
    public function compileSpatialTouches(string $column, string $geometry): string
    {
        return "ST_Touches({$this->wrap($column)}, ST_GeomFromText(?, 4326))";
    }

    /**
     * Compile a PostGIS overlaps clause
     */
    public function compileSpatialOverlaps(string $column, string $geometry): string
    {
        return "ST_Overlaps({$this->wrap($column)}, ST_GeomFromText(?, 4326))";
    }
}
