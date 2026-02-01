<?php

namespace Framework\Database\ORM\Grammar;

/**
 * Abstract base class for database-specific SQL generation
 *
 * Each database driver (PostgreSQL, MySQL, SQLite, SQL Server) has its own
 * grammar implementation that converts QueryBuilder components into valid SQL.
 */
abstract class Grammar
{
    /**
     * Table prefix
     */
    protected string $tablePrefix = '';

    /**
     * Grammar-specific operators
     */
    protected array $operators = [];

    /**
     * Compile a select query
     */
    abstract public function compileSelect(array $components): string;

    /**
     * Compile an insert query
     */
    abstract public function compileInsert(string $table, array $values): string;

    /**
     * Compile an update query
     */
    abstract public function compileUpdate(string $table, array $values, array $wheres): string;

    /**
     * Compile a delete query
     */
    abstract public function compileDelete(string $table, array $wheres): string;

    /**
     * Wrap a table in keyword identifiers
     */
    abstract public function wrapTable(string $table): string;

    /**
     * Wrap a value in keyword identifiers
     */
    abstract public function wrap(string $value): string;

    /**
     * Get the format for database stored dates
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Compile the "select" portion of the query
     */
    protected function compileColumns(array $columns): string
    {
        if (empty($columns)) {
            return '*';
        }

        $wrapped = array_map(function ($column) {
            return $column === '*' ? $column : $this->wrap($column);
        }, $columns);

        return implode(', ', $wrapped);
    }

    /**
     * Compile the "from" portion of the query
     */
    protected function compileFrom(string $table): string
    {
        return 'FROM ' . $this->wrapTable($table);
    }

    /**
     * Compile the "where" portions of the query
     */
    protected function compileWheres(array $wheres): string
    {
        if (empty($wheres)) {
            return '';
        }

        $sql = [];

        foreach ($wheres as $index => $where) {
            $boolean = $index === 0 ? 'WHERE' : $where['boolean'];
            $sql[] = $boolean . ' ' . $this->compileWhere($where);
        }

        return implode(' ', $sql);
    }

    /**
     * Compile a single where clause
     */
    protected function compileWhere(array $where): string
    {
        $type = $where['type'] ?? 'basic';

        switch ($type) {
            case 'basic':
                return $this->compileBasicWhere($where);
            case 'in':
                return $this->compileInWhere($where);
            case 'null':
                return $this->compileNullWhere($where);
            case 'between':
                return $this->compileBetweenWhere($where);
            case 'nested':
                return $this->compileNestedWhere($where);
            case 'raw':
                return $where['sql'];
            default:
                return '';
        }
    }

    /**
     * Compile a nested where clause
     */
    protected function compileNestedWhere(array $where): string
    {
        $query = $where['query'];
        $nestedWheres = $this->compileWheres($query->components['wheres'] ?? []);

        // Remove the leading "WHERE" and wrap in parentheses
        $nestedWheres = preg_replace('/^WHERE\s+/', '', $nestedWheres);

        return "({$nestedWheres})";
    }

    /**
     * Compile a basic where clause
     */
    protected function compileBasicWhere(array $where): string
    {
        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?';
    }

    /**
     * Compile a where in clause
     */
    protected function compileInWhere(array $where): string
    {
        $values = str_repeat('?, ', count($where['values']) - 1) . '?';
        $not = $where['not'] ?? false ? 'NOT ' : '';

        return $this->wrap($where['column']) . ' ' . $not . 'IN (' . $values . ')';
    }

    /**
     * Compile a where null clause
     */
    protected function compileNullWhere(array $where): string
    {
        $not = $where['not'] ?? false ? 'NOT ' : '';
        return $this->wrap($where['column']) . ' IS ' . $not . 'NULL';
    }

    /**
     * Compile a where between clause
     */
    protected function compileBetweenWhere(array $where): string
    {
        $not = $where['not'] ?? false ? 'NOT ' : '';
        return $this->wrap($where['column']) . ' ' . $not . 'BETWEEN ? AND ?';
    }

    /**
     * Compile the "join" portions of the query
     */
    protected function compileJoins(array $joins): string
    {
        if (empty($joins)) {
            return '';
        }

        $sql = [];

        foreach ($joins as $join) {
            $type = strtoupper($join['type']);
            $table = $this->wrapTable($join['table']);
            $first = $this->wrap($join['first']);
            $operator = $join['operator'];
            $second = $this->wrap($join['second']);

            $sql[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        }

        return implode(' ', $sql);
    }

    /**
     * Compile the "order by" portion of the query
     */
    protected function compileOrders(array $orders): string
    {
        if (empty($orders)) {
            return '';
        }

        $sql = [];

        foreach ($orders as $order) {
            $direction = strtoupper($order['direction'] ?? 'ASC');

            // Handle spatial distance ordering
            if (isset($order['type']) && $order['type'] === 'spatial') {
                $column = $this->wrap($order['column']);
                $srid = $order['srid'] ?? 4326;
                $sql[] = "ST_Distance({$column}, ST_GeomFromText(?, {$srid})) {$direction}";
            } else {
                $sql[] = $this->wrap($order['column']) . ' ' . $direction;
            }
        }

        return 'ORDER BY ' . implode(', ', $sql);
    }

    /**
     * Compile the "group by" portion of the query
     */
    protected function compileGroups(array $groups): string
    {
        if (empty($groups)) {
            return '';
        }

        $wrapped = array_map(fn($group) => $this->wrap($group), $groups);

        return 'GROUP BY ' . implode(', ', $wrapped);
    }

    /**
     * Compile the "having" portion of the query
     */
    protected function compileHavings(array $havings): string
    {
        if (empty($havings)) {
            return '';
        }

        $sql = [];

        foreach ($havings as $index => $having) {
            $boolean = $index === 0 ? 'HAVING' : $having['boolean'];
            $column = $this->wrap($having['column']);
            $operator = $having['operator'];

            $sql[] = "{$boolean} {$column} {$operator} ?";
        }

        return implode(' ', $sql);
    }

    /**
     * Compile the "limit" portion of the query
     */
    protected function compileLimit(?int $limit): string
    {
        if ($limit === null) {
            return '';
        }

        return 'LIMIT ' . (int)$limit;
    }

    /**
     * Compile the "offset" portion of the query
     */
    protected function compileOffset(?int $offset): string
    {
        if ($offset === null) {
            return '';
        }

        return 'OFFSET ' . (int)$offset;
    }

    /**
     * Determine if the grammar supports savepoints
     */
    public function supportsSavepoints(): bool
    {
        return true;
    }

    /**
     * Compile the SQL statement to define a savepoint
     */
    public function compileSavepoint(string $name): string
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * Compile the SQL statement to rollback to a savepoint
     */
    public function compileSavepointRollback(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }
}
