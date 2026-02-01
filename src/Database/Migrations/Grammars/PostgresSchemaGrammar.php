<?php

namespace Vireo\Framework\Database\Migrations\Grammars;

use Vireo\Framework\Database\Migrations\Blueprint;
use Vireo\Framework\Database\Migrations\ColumnDefinition;

/**
 * PostgreSQL Schema Grammar
 *
 * Generates PostgreSQL-specific DDL statements including PostGIS spatial types
 */
class PostgresSchemaGrammar extends SchemaGrammar
{
    /**
     * Compile a create table statement
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        $sql = "CREATE TABLE {$this->wrapTable($blueprint->table)} (";

        // Add columns
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

        $sql .= ')';

        // Create indexes, foreign keys, etc. as separate statements
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
            $statements[] = "ALTER TABLE {$this->wrapTable($blueprint->table)} ADD COLUMN " .
                $this->compileColumnDefinition($column);
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
        return "ALTER TABLE {$this->wrapTable($from)} RENAME TO {$this->wrapTable($to)}";
    }

    /**
     * Compile a column definition
     */
    protected function compileColumnDefinition(ColumnDefinition $column): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->getType($column);

        // Add modifiers with a space separator
        $modifiers = $this->getModifiers($column);
        if (!empty($modifiers)) {
            $sql .= ' ' . $modifiers;
        }

        return $sql;
    }

    /**
     * Get the SQL type for a column
     */
    protected function getType(ColumnDefinition $column): string
    {
        // Handle auto-increment first
        if ($column->get('autoIncrement', false)) {
            return match ($column->type) {
                'tinyInteger', 'smallInteger' => 'SMALLSERIAL',
                'integer' => 'SERIAL',
                'bigInteger' => 'BIGSERIAL',
                default => $this->getTypeWithoutAutoIncrement($column),
            };
        }

        return $this->getTypeWithoutAutoIncrement($column);
    }

    /**
     * Get type without auto-increment
     */
    protected function getTypeWithoutAutoIncrement(ColumnDefinition $column): string
    {
        return match ($column->type) {
            // Integer types
            'tinyInteger' => 'SMALLINT',
            'smallInteger' => 'SMALLINT',
            'integer' => 'INTEGER',
            'bigInteger' => 'BIGINT',

            // String types
            'string' => 'VARCHAR(' . ($column->get('length', 255)) . ')',
            'char' => 'CHAR(' . ($column->get('length', 255)) . ')',
            'text' => 'TEXT',
            'mediumText' => 'TEXT',
            'longText' => 'TEXT',

            // Numeric types
            'decimal' => 'DECIMAL(' . $column->get('precision', 8) . ',' . $column->get('scale', 2) . ')',
            'float' => 'REAL',
            'double' => 'DOUBLE PRECISION',

            // Boolean
            'boolean' => 'BOOLEAN',

            // Date/Time types
            'date' => 'DATE',
            'datetime' => 'TIMESTAMP',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',

            // JSON types
            'json' => 'JSON',
            'jsonb' => 'JSONB',

            // Binary
            'binary' => 'BYTEA',

            // UUID
            'uuid' => 'UUID',

            // Array (PostgreSQL specific)
            'array' => $this->getArrayType($column),

            // Enum
            'enum' => $this->getEnumType($column),

            // PostGIS Spatial types
            'point' => $this->getSpatialType('POINT', $column),
            'linestring' => $this->getSpatialType('LINESTRING', $column),
            'polygon' => $this->getSpatialType('POLYGON', $column),
            'multipoint' => $this->getSpatialType('MULTIPOINT', $column),
            'multilinestring' => $this->getSpatialType('MULTILINESTRING', $column),
            'multipolygon' => $this->getSpatialType('MULTIPOLYGON', $column),
            'geometrycollection' => $this->getSpatialType('GEOMETRYCOLLECTION', $column),
            'geography' => $this->getGeographyType($column),
            'geometry' => $this->getGeometryType($column),

            default => 'VARCHAR(255)',
        };
    }

    /**
     * Get array type definition
     */
    protected function getArrayType(ColumnDefinition $column): string
    {
        $arrayType = $column->get('arrayType', 'text');
        return strtoupper($arrayType) . '[]';
    }

    /**
     * Get enum type definition
     */
    protected function getEnumType(ColumnDefinition $column): string
    {
        $allowed = $column->get('allowed', []);
        $values = array_map(fn($v) => $this->quoteString($v), $allowed);
        return 'VARCHAR(255) CHECK (' . $this->wrap($column->name) . ' IN (' . implode(', ', $values) . '))';
    }

    /**
     * Get spatial type definition (geometry)
     */
    protected function getSpatialType(string $type, ColumnDefinition $column): string
    {
        $srid = $column->get('srid', 4326);
        return "geometry({$type}, {$srid})";
    }

    /**
     * Get geography type definition
     */
    protected function getGeographyType(ColumnDefinition $column): string
    {
        $spatialType = $column->get('spatialType', 'POINT');
        $srid = $column->get('srid', 4326);
        return "geography({$spatialType}, {$srid})";
    }

    /**
     * Get geometry type definition
     */
    protected function getGeometryType(ColumnDefinition $column): string
    {
        $spatialType = $column->get('spatialType', 'POINT');
        $srid = $column->get('srid', 4326);
        return "geometry({$spatialType}, {$srid})";
    }

    /**
     * Get increment modifier
     * Note: For PostgreSQL, auto-increment is handled in getType() via SERIAL types
     */
    protected function modifyIncrement(ColumnDefinition $column): ?string
    {
        // Auto-increment already handled in type (SERIAL, BIGSERIAL, etc.)
        return null;
    }

    /**
     * Get primary key modifier
     */
    protected function modifyPrimary(ColumnDefinition $column): ?string
    {
        if ($column->get('primary', false)) {
            return 'PRIMARY KEY';
        }
        return null;
    }

    /**
     * Get unique constraint modifier
     */
    protected function modifyUnique(ColumnDefinition $column): ?string
    {
        if ($column->get('unique', false)) {
            return 'UNIQUE';
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
        $name = $index->name ?? $this->generateIndexName($blueprint->table, $index->columns, 'idx');

        $sql = "CREATE INDEX {$this->wrap($name)} ON {$this->wrapTable($blueprint->table)}";

        if ($index->algorithm) {
            $sql .= " USING {$index->algorithm}";
        }

        $sql .= " (" . implode(', ', $columns) . ")";

        return $sql;
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
     * Compile a spatial index command (PostGIS)
     */
    public function compileSpatialIndex(Blueprint $blueprint, array $command): string
    {
        $index = $command['definition'];
        $columns = array_map(fn($col) => $this->wrap($col), $index->columns);
        $name = $index->name ?? $this->generateIndexName($blueprint->table, $index->columns, 'spatial');

        // Spatial indexes in PostgreSQL use GIST algorithm
        $algorithm = $index->algorithm ?? 'gist';

        return "CREATE INDEX {$this->wrap($name)} ON {$this->wrapTable($blueprint->table)} " .
            "USING {$algorithm} (" . implode(', ', $columns) . ")";
    }

    /**
     * Compile a fulltext index command
     */
    public function compileFulltext(Blueprint $blueprint, array $command): string
    {
        $index = $command['definition'];
        $columns = array_map(fn($col) => $this->wrap($col), $index->columns);
        $name = $index->name ?? $this->generateIndexName($blueprint->table, $index->columns, 'fulltext');

        // PostgreSQL uses GIN or GiST for full-text search
        return "CREATE INDEX {$this->wrap($name)} ON {$this->wrapTable($blueprint->table)} " .
            "USING gin(to_tsvector('english', " . implode(" || ' ' || ", $columns) . "))";
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
     * Compile drop index command
     */
    public function compileDropIndex(Blueprint $blueprint, array $command): string
    {
        return "DROP INDEX IF EXISTS {$this->wrap($command['name'])}";
    }

    /**
     * Compile drop foreign key command
     */
    public function compileDropForeign(Blueprint $blueprint, array $command): string
    {
        return "ALTER TABLE {$this->wrapTable($blueprint->table)} DROP CONSTRAINT {$this->wrap($command['name'])}";
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
        if ($value === '*') {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

}
