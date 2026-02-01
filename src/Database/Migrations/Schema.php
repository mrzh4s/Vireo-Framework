<?php

namespace Vireo\Framework\Database\Migrations;

use Vireo\Framework\Database\Migrations\Grammars\SchemaGrammar;
use Vireo\Framework\Database\Migrations\Grammars\PostgresSchemaGrammar;
use Vireo\Framework\Database\Migrations\Grammars\MySQLSchemaGrammar;
use Vireo\Framework\Database\Migrations\Grammars\SQLiteSchemaGrammar;
use Vireo\Framework\Database\DB;
use PDO;

/**
 * Schema Builder Facade
 *
 * Static interface for database schema operations
 */
class Schema
{
    /**
     * Current connection name
     */
    protected static ?string $connection = null;

    /**
     * Set the connection for schema operations
     */
    public static function connection(?string $name): static
    {
        static::$connection = $name;
        return new static();
    }

    /**
     * Create a new table
     */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'create');
        $callback($blueprint);

        $pdo = static::getPDO();
        $grammar = static::getGrammar($pdo);

        // Execute create table statement
        $sql = $grammar->compileCreate($blueprint);
        $pdo->exec($sql);

        // Execute additional commands (indexes, foreign keys, etc.)
        static::executeCommands($blueprint, $grammar, $pdo);
    }

    /**
     * Modify an existing table
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'alter');
        $callback($blueprint);

        $pdo = static::getPDO();
        $grammar = static::getGrammar($pdo);

        // Execute alter statements
        $statements = $grammar->compileAlter($blueprint);
        foreach ($statements as $sql) {
            if (!empty($sql)) {
                $pdo->exec($sql);
            }
        }
    }

    /**
     * Drop a table
     */
    public static function drop(string $table): void
    {
        $pdo = static::getPDO();
        $grammar = static::getGrammar($pdo);

        $sql = $grammar->compileDrop($table);
        $pdo->exec($sql);
    }

    /**
     * Drop a table if it exists
     */
    public static function dropIfExists(string $table): void
    {
        $pdo = static::getPDO();
        $grammar = static::getGrammar($pdo);

        $sql = $grammar->compileDropIfExists($table);
        $pdo->exec($sql);
    }

    /**
     * Rename a table
     */
    public static function rename(string $from, string $to): void
    {
        $pdo = static::getPDO();
        $grammar = static::getGrammar($pdo);

        $sql = $grammar->compileRename($from, $to);
        $pdo->exec($sql);
    }

    /**
     * Check if a table exists
     */
    public static function hasTable(string $table): bool
    {
        $pdo = static::getPDO();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $sql = match ($driver) {
            'pgsql' => static::compileHasTablePostgres($table),
            'mysql' => static::compileHasTableMysql($table),
            'sqlite' => static::compileHasTableSqlite($table),
            default => static::compileHasTablePostgres($table),
        };

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        return (bool) $result;
    }

    /**
     * Check if a column exists
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $pdo = static::getPDO();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $sql = match ($driver) {
            'pgsql' => static::compileHasColumnPostgres($table, $column),
            'mysql' => static::compileHasColumnMysql($table, $column),
            'sqlite' => static::compileHasColumnSqlite($table, $column),
            default => static::compileHasColumnPostgres($table, $column),
        };

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        return (bool) $result;
    }

    /**
     * Get column listing for a table
     */
    public static function getColumnListing(string $table): array
    {
        $pdo = static::getPDO();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $sql = match ($driver) {
            'pgsql' => static::compileGetColumnsPostgres($table),
            'mysql' => static::compileGetColumnsMysql($table),
            'sqlite' => static::compileGetColumnsSqlite($table),
            default => static::compileGetColumnsPostgres($table),
        };

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $columns;
    }

    /**
     * Execute blueprint commands (indexes, foreign keys, etc.)
     */
    protected static function executeCommands(Blueprint $blueprint, SchemaGrammar $grammar, PDO $pdo): void
    {
        foreach ($blueprint->getCommands() as $command) {
            $type = $command['type'];

            // Skip primary key as it's handled in CREATE TABLE
            if ($type === 'primary') {
                continue;
            }

            $method = 'compile' . ucfirst($type);

            if (method_exists($grammar, $method)) {
                $sql = $grammar->$method($blueprint, $command);

                if (is_array($sql)) {
                    foreach ($sql as $statement) {
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                } elseif (!empty($sql)) {
                    $pdo->exec($sql);
                }
            }
        }
    }

    /**
     * Get PDO connection
     */
    protected static function getPDO(): PDO
    {
        return DB::connection(static::$connection);
    }

    /**
     * Get schema grammar for the connection
     */
    protected static function getGrammar(PDO $pdo): SchemaGrammar
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'pgsql' => new PostgresSchemaGrammar(),
            'mysql' => new MySQLSchemaGrammar(),
            'sqlite' => new SQLiteSchemaGrammar(),
            default => new PostgresSchemaGrammar(),
        };
    }

    /**
     * Compile hasTable query for PostgreSQL
     */
    protected static function compileHasTablePostgres(string $table): string
    {
        // Handle schema.table notation
        if (str_contains($table, '.')) {
            [$schema, $tableName] = explode('.', $table);
            return "SELECT EXISTS (
                SELECT FROM information_schema.tables
                WHERE table_schema = '{$schema}'
                AND table_name = '{$tableName}'
            )";
        }

        return "SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_name = '{$table}'
        )";
    }

    /**
     * Compile hasColumn query for PostgreSQL
     */
    protected static function compileHasColumnPostgres(string $table, string $column): string
    {
        if (str_contains($table, '.')) {
            [$schema, $tableName] = explode('.', $table);
            return "SELECT EXISTS (
                SELECT FROM information_schema.columns
                WHERE table_schema = '{$schema}'
                AND table_name = '{$tableName}'
                AND column_name = '{$column}'
            )";
        }

        return "SELECT EXISTS (
            SELECT FROM information_schema.columns
            WHERE table_name = '{$table}'
            AND column_name = '{$column}'
        )";
    }

    /**
     * Compile getColumns query for PostgreSQL
     */
    protected static function compileGetColumnsPostgres(string $table): string
    {
        if (str_contains($table, '.')) {
            [$schema, $tableName] = explode('.', $table);
            return "SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = '{$schema}'
                AND table_name = '{$tableName}'
                ORDER BY ordinal_position";
        }

        return "SELECT column_name
            FROM information_schema.columns
            WHERE table_name = '{$table}'
            ORDER BY ordinal_position";
    }

    /**
     * Compile hasTable query for MySQL
     */
    protected static function compileHasTableMysql(string $table): string
    {
        return "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = '{$table}'";
    }

    /**
     * Compile hasColumn query for MySQL
     */
    protected static function compileHasColumnMysql(string $table, string $column): string
    {
        return "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = '{$table}' AND column_name = '{$column}'";
    }

    /**
     * Compile getColumns query for MySQL
     */
    protected static function compileGetColumnsMysql(string $table): string
    {
        return "SELECT column_name FROM information_schema.columns WHERE table_name = '{$table}' ORDER BY ordinal_position";
    }

    /**
     * Compile hasTable query for SQLite
     */
    protected static function compileHasTableSqlite(string $table): string
    {
        return "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = '{$table}'";
    }

    /**
     * Compile hasColumn query for SQLite
     */
    protected static function compileHasColumnSqlite(string $table, string $column): string
    {
        return "SELECT COUNT(*) FROM pragma_table_info('{$table}') WHERE name = '{$column}'";
    }

    /**
     * Compile getColumns query for SQLite
     */
    protected static function compileGetColumnsSqlite(string $table): string
    {
        return "SELECT name FROM pragma_table_info('{$table}') ORDER BY cid";
    }
}
