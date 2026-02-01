<?php

namespace Framework\Database\ORM;

use Framework\Database\ORM\Grammar\Grammar;
use Framework\Database\ORM\Grammar\PostgresGrammar;
use Framework\Database\ORM\Grammar\MySQLGrammar;
use Framework\Database\ORM\Grammar\SQLiteGrammar;
use Framework\Database\DB;
use PDO;
use PDOStatement;

/**
 * Query Builder
 *
 * Fluent interface for building and executing database queries
 */
class QueryBuilder
{
    /**
     * PDO connection
     */
    protected PDO $pdo;

    /**
     * Grammar for SQL generation
     */
    protected Grammar $grammar;

    /**
     * Connection name
     */
    protected ?string $connection = null;

    /**
     * Table name
     */
    protected ?string $table = null;

    /**
     * Query components
     */
    protected array $components = [
        'columns' => [],
        'from' => null,
        'joins' => [],
        'wheres' => [],
        'groups' => [],
        'havings' => [],
        'orders' => [],
        'limit' => null,
        'offset' => null,
        'selectRaw' => [],
    ];

    /**
     * Query bindings
     */
    protected array $bindings = [];

    /**
     * Create a new query builder instance
     */
    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
        $this->pdo = DB::connection($connection);
        $this->grammar = $this->resolveGrammar();
    }

    /**
     * Set the table for the query
     */
    public function table(string $table): static
    {
        $this->components['from'] = $table;
        $this->table = $table;
        return $this;
    }

    /**
     * Alias for table()
     */
    public function from(string $table): static
    {
        return $this->table($table);
    }

    /**
     * Set the columns to select
     */
    public function select(...$columns): static
    {
        if (empty($columns)) {
            $columns = ['*'];
        }

        $this->components['columns'] = $columns;
        return $this;
    }

    /**
     * Add a raw select expression
     */
    public function selectRaw(string $expression): static
    {
        $this->components['selectRaw'][] = $expression;
        return $this;
    }

    /**
     * Add a basic where clause
     */
    public function where(string|callable $column, mixed $operator = null, mixed $value = null): static
    {
        // Handle closures for nested where clauses
        if (is_callable($column)) {
            return $this->whereNested($column);
        }

        // Handle two arguments (column, value) - assume = operator
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->components['wheres'][] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an "or where" clause
     */
    public function orWhere(string|callable $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->components['wheres'][] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add a where in clause
     */
    public function whereIn(string $column, array $values): static
    {
        $this->components['wheres'][] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'not' => false,
            'boolean' => 'AND',
        ];

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * Add a where not in clause
     */
    public function whereNotIn(string $column, array $values): static
    {
        $this->components['wheres'][] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'not' => true,
            'boolean' => 'AND',
        ];

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * Add a where null clause
     */
    public function whereNull(string $column): static
    {
        $this->components['wheres'][] = [
            'type' => 'null',
            'column' => $column,
            'not' => false,
            'boolean' => 'AND',
        ];

        return $this;
    }

    /**
     * Add a where not null clause
     */
    public function whereNotNull(string $column): static
    {
        $this->components['wheres'][] = [
            'type' => 'null',
            'column' => $column,
            'not' => true,
            'boolean' => 'AND',
        ];

        return $this;
    }

    /**
     * Add a where between clause
     */
    public function whereBetween(string $column, array $values): static
    {
        $this->components['wheres'][] = [
            'type' => 'between',
            'column' => $column,
            'values' => $values,
            'not' => false,
            'boolean' => 'AND',
        ];

        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];

        return $this;
    }

    /**
     * Add a where not between clause
     */
    public function whereNotBetween(string $column, array $values): static
    {
        $this->components['wheres'][] = [
            'type' => 'between',
            'column' => $column,
            'values' => $values,
            'not' => true,
            'boolean' => 'AND',
        ];

        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];

        return $this;
    }

    /**
     * Add a raw where clause
     */
    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->components['wheres'][] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => 'AND',
        ];

        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Add a nested where clause (for grouping)
     */
    protected function whereNested(callable $callback): static
    {
        $query = new static($this->connection);
        $query->table($this->components['from']);

        $callback($query);

        // Add nested where conditions
        if (!empty($query->components['wheres'])) {
            $this->components['wheres'][] = [
                'type' => 'nested',
                'query' => $query,
                'boolean' => 'AND',
            ];

            // Merge bindings
            $this->bindings = array_merge($this->bindings, $query->getBindings());
        }

        return $this;
    }

    /**
     * Add a join clause
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): static
    {
        $this->components['joins'][] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    /**
     * Add a left join clause
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a right join clause
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * Add a cross join clause
     */
    public function crossJoin(string $table): static
    {
        $this->components['joins'][] = [
            'table' => $table,
            'type' => 'cross',
        ];

        return $this;
    }

    /**
     * Add an order by clause
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->components['orders'][] = compact('column', 'direction');
        return $this;
    }

    /**
     * Add a descending order by clause
     */
    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add a group by clause
     */
    public function groupBy(...$groups): static
    {
        foreach ($groups as $group) {
            $this->components['groups'][] = $group;
        }

        return $this;
    }

    /**
     * Add a having clause
     */
    public function having(string $column, string $operator, mixed $value): static
    {
        $this->components['havings'][] = compact('column', 'operator', 'value');
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Set the limit
     */
    public function limit(int $value): static
    {
        $this->components['limit'] = $value;
        return $this;
    }

    /**
     * Alias for limit
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Set the offset
     */
    public function offset(int $value): static
    {
        $this->components['offset'] = $value;
        return $this;
    }

    /**
     * Alias for offset
     */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    /**
     * Execute the query and get all results
     */
    public function get(): Collection
    {
        $sql = $this->grammar->compileSelect($this->components);
        $stmt = $this->execute($sql, $this->bindings);

        return new Collection($stmt->fetchAll());
    }

    /**
     * Execute the query and get the first result
     */
    public function first(): ?object
    {
        $results = $this->limit(1)->get();
        return $results->first();
    }

    /**
     * Execute the query and get a single column value
     */
    public function value(string $column): mixed
    {
        $result = $this->select($column)->first();
        return $result?->$column ?? null;
    }

    /**
     * Get the count of records
     */
    public function count(string $column = '*'): int
    {
        return (int)$this->aggregate('COUNT', $column);
    }

    /**
     * Get the sum of column values
     */
    public function sum(string $column): float
    {
        return (float)$this->aggregate('SUM', $column);
    }

    /**
     * Get the average of column values
     */
    public function avg(string $column): float
    {
        return (float)$this->aggregate('AVG', $column);
    }

    /**
     * Get the minimum column value
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Get the maximum column value
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Execute an aggregate function
     */
    protected function aggregate(string $function, string $column): mixed
    {
        $this->components['columns'] = ["{$function}({$column}) as aggregate"];
        $sql = $this->grammar->compileSelect($this->components);
        $stmt = $this->execute($sql, $this->bindings);

        $result = $stmt->fetch();
        return $result?->aggregate ?? null;
    }

    /**
     * Check if any records exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Check if no records exist
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Insert a record
     */
    public function insert(array $values): bool
    {
        // Handle single row
        if (!isset($values[0])) {
            $values = [$values];
        }

        $sql = $this->grammar->compileInsert($this->components['from'], $values);
        $bindings = $this->flattenInsertValues($values);

        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount() > 0;
    }

    /**
     * Insert a record and get the ID
     */
    public function insertGetId(array $values, string $sequence = 'id'): mixed
    {
        if (method_exists($this->grammar, 'compileInsertGetId')) {
            $sql = $this->grammar->compileInsertGetId($this->components['from'], $values, $sequence);
            $bindings = array_values($values);

            $stmt = $this->execute($sql, $bindings);
            $result = $stmt->fetch();

            return $result?->$sequence ?? null;
        }

        $this->insert([$values]);
        return $this->pdo->lastInsertId($sequence);
    }

    /**
     * Update records
     */
    public function update(array $values): int
    {
        $sql = $this->grammar->compileUpdate($this->components['from'], $values, $this->components['wheres']);
        $bindings = array_merge(array_values($values), $this->bindings);

        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this->components['from'], $this->components['wheres']);
        $stmt = $this->execute($sql, $this->bindings);

        return $stmt->rowCount();
    }

    /**
     * Truncate the table
     */
    public function truncate(): void
    {
        if (method_exists($this->grammar, 'compileTruncate')) {
            $sql = $this->grammar->compileTruncate($this->components['from']);
        } else {
            $sql = 'TRUNCATE TABLE ' . $this->components['from'];
        }

        $this->pdo->exec($sql);
    }

    /**
     * Chunk results
     */
    public function chunk(int $count, callable $callback): void
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $count)->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results, $page) === false) {
                break;
            }

            $page++;
        } while ($results->count() === $count);
    }

    /**
     * Paginate results
     */
    protected function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Execute a raw SQL query
     */
    public function raw(string $sql, array $bindings = []): Collection
    {
        $stmt = $this->execute($sql, $bindings);
        return new Collection($stmt->fetchAll());
    }

    /**
     * Execute a prepared statement
     */
    protected function execute(string $sql, array $bindings = []): PDOStatement
    {
        $start = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $time = (microtime(true) - $start) * 1000; // milliseconds

        // Log slow queries
        $threshold = (float)env('SLOW_QUERY_THRESHOLD', 100);
        if ($time > $threshold) {
            logger('database')->warning('Slow query detected', [
                'query' => $sql,
                'bindings' => env('LOG_QUERY_BINDINGS', true) ? $bindings : '[hidden]',
                'time' => round($time, 2) . 'ms',
                'threshold' => $threshold . 'ms'
            ]);
        }

        return $stmt;
    }

    /**
     * Flatten insert values for binding
     */
    protected function flattenInsertValues(array $values): array
    {
        $bindings = [];

        foreach ($values as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }

    /**
     * Resolve the grammar for the connection
     */
    protected function resolveGrammar(): Grammar
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'pgsql' => new PostgresGrammar(),
            'mysql' => new MySQLGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default => new PostgresGrammar(),
        };
    }

    /**
     * Get the SQL query string
     */
    public function toSql(): string
    {
        return $this->grammar->compileSelect($this->components);
    }

    /**
     * Get the query bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Dump the query SQL and bindings
     */
    public function dump(): static
    {
        if (function_exists('dump')) {
            dump($this->toSql(), $this->bindings);
        } else {
            var_dump([
                'sql' => $this->toSql(),
                'bindings' => $this->bindings
            ]);
        }
        return $this;
    }

    /**
     * Dump the query and die
     */
    public function dd(): void
    {
        if (function_exists('dd')) {
            dd($this->toSql(), $this->bindings);
        } else {
            $this->dump();
            exit(1);
        }
    }
}
