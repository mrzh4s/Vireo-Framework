<?php

namespace Vireo\Framework\Database\Migrations;

/**
 * Blueprint
 *
 * Fluent table definition builder for migrations
 */
class Blueprint
{
    /**
     * Table name
     */
    public string $table;

    /**
     * Mode (create or alter)
     */
    public string $mode;

    /**
     * Column definitions
     */
    public array $columns = [];

    /**
     * Commands (indexes, foreign keys, etc.)
     */
    public array $commands = [];

    /**
     * Create a new blueprint instance
     */
    public function __construct(string $table, string $mode = 'create')
    {
        $this->table = $table;
        $this->mode = $mode;
    }

    // ==================== Primary Key ====================

    /**
     * Create an auto-incrementing primary key column
     */
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($name);
    }

    /**
     * Create a UUID column
     */
    public function uuid(string $name = 'id'): ColumnDefinition
    {
        $column = $this->addColumn('uuid', $name);
        return $column;
    }

    /**
     * Create a foreign ID column (unsigned big integer)
     */
    public function foreignId(string $name): ColumnDefinition
    {
        return $this->unsignedBigInteger($name);
    }

    /**
     * Create a foreign UUID column
     */
    public function foreignUuid(string $name): ColumnDefinition
    {
        return $this->addColumn('uuid', $name);
    }

    // ==================== Integer Columns ====================

    /**
     * Create a tiny integer column
     */
    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $name);
    }

    /**
     * Create a small integer column
     */
    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $name);
    }

    /**
     * Create an integer column
     */
    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn('integer', $name);
    }

    /**
     * Create a big integer column
     */
    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $name);
    }

    /**
     * Create an unsigned tiny integer column
     */
    public function unsignedTinyInteger(string $name): ColumnDefinition
    {
        return $this->tinyInteger($name)->unsigned();
    }

    /**
     * Create an unsigned small integer column
     */
    public function unsignedSmallInteger(string $name): ColumnDefinition
    {
        return $this->smallInteger($name)->unsigned();
    }

    /**
     * Create an unsigned integer column
     */
    public function unsignedInteger(string $name): ColumnDefinition
    {
        return $this->integer($name)->unsigned();
    }

    /**
     * Create an unsigned big integer column
     */
    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned();
    }

    /**
     * Create an auto-incrementing integer column
     */
    public function increments(string $name): ColumnDefinition
    {
        $column = $this->integer($name);
        $column->autoIncrement()->primary();
        return $column;
    }

    /**
     * Create an auto-incrementing big integer column
     */
    public function bigIncrements(string $name): ColumnDefinition
    {
        $column = $this->bigInteger($name);
        $column->autoIncrement()->primary();
        return $column;
    }

    // ==================== String Columns ====================

    /**
     * Create a string column
     */
    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $name, ['length' => $length]);
    }

    /**
     * Create a char column
     */
    public function char(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $name, ['length' => $length]);
    }

    /**
     * Create a text column
     */
    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn('text', $name);
    }

    /**
     * Create a medium text column
     */
    public function mediumText(string $name): ColumnDefinition
    {
        return $this->addColumn('mediumText', $name);
    }

    /**
     * Create a long text column
     */
    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn('longText', $name);
    }

    // ==================== Numeric Columns ====================

    /**
     * Create a decimal column
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $name, compact('precision', 'scale'));
    }

    /**
     * Create a float column
     */
    public function float(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('float', $name, compact('precision', 'scale'));
    }

    /**
     * Create a double column
     */
    public function double(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('double', $name, compact('precision', 'scale'));
    }

    // ==================== Boolean Column ====================

    /**
     * Create a boolean column
     */
    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn('boolean', $name);
    }

    // ==================== Date/Time Columns ====================

    /**
     * Create a date column
     */
    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn('date', $name);
    }

    /**
     * Create a datetime column
     */
    public function datetime(string $name): ColumnDefinition
    {
        return $this->addColumn('datetime', $name);
    }

    /**
     * Create a timestamp column
     */
    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn('timestamp', $name);
    }

    /**
     * Create a time column
     */
    public function time(string $name): ColumnDefinition
    {
        return $this->addColumn('time', $name);
    }

    /**
     * Create timestamp columns (created_at, updated_at)
     */
    public function timestamps(bool $nullable = false): void
    {
        $created = $this->timestamp('created_at');
        $updated = $this->timestamp('updated_at');

        if ($nullable) {
            $created->nullable();
            $updated->nullable();
        }
    }

    /**
     * Create a soft delete column (deleted_at)
     */
    public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($column)->nullable();
    }

    /**
     * Create a remember token column for authentication
     */
    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Create polymorphic relation columns (morphable_type and morphable_id)
     */
    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->unsignedBigInteger("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Create nullable polymorphic relation columns
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->unsignedBigInteger("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Create UUID-based polymorphic relation columns
     */
    public function uuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->addColumn('uuid', "{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Create nullable UUID-based polymorphic relation columns
     */
    public function nullableUuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->addColumn('uuid', "{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    // ==================== JSON Columns ====================

    /**
     * Create a JSON column
     */
    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn('json', $name);
    }

    /**
     * Create a JSONB column (PostgreSQL)
     */
    public function jsonb(string $name): ColumnDefinition
    {
        return $this->addColumn('jsonb', $name);
    }

    // ==================== Binary Columns ====================

    /**
     * Create a binary column
     */
    public function binary(string $name): ColumnDefinition
    {
        return $this->addColumn('binary', $name);
    }

    // ==================== Array Columns (PostgreSQL) ====================

    /**
     * Create an array column (PostgreSQL)
     */
    public function array(string $name, string $type = 'text'): ColumnDefinition
    {
        return $this->addColumn('array', $name, ['arrayType' => $type]);
    }

    // ==================== Enum Column ====================

    /**
     * Create an enum column
     */
    public function enum(string $name, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $name, ['allowed' => $allowed]);
    }

    // ==================== PostGIS Spatial Columns ====================

    /**
     * Create a PostGIS point column
     */
    public function point(string $name, int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('point', $name, ['srid' => $srid]);
    }

    /**
     * Create a PostGIS linestring column
     */
    public function linestring(string $name, int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('linestring', $name, ['srid' => $srid]);
    }

    /**
     * Create a PostGIS polygon column
     */
    public function polygon(string $name, int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('polygon', $name, ['srid' => $srid]);
    }

    /**
     * Create a PostGIS multipoint column
     */
    public function multipoint(string $name, int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('multipoint', $name, ['srid' => $srid]);
    }

    /**
     * Create a PostGIS multilinestring column
     */
    public function multilinestring(string $name, int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('multilinestring', $name, ['srid' => $srid]);
    }

    /**
     * Create a PostGIS multipolygon column
     */
    public function multipolygon(string $name, int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('multipolygon', $name, ['srid' => $srid]);
    }

    /**
     * Create a PostGIS geometry collection column
     */
    public function geometrycollection(string $name, int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('geometrycollection', $name, ['srid' => $srid]);
    }

    /**
     * Create a PostGIS geography column
     */
    public function geography(string $name, string $type = 'POINT', int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('geography', $name, ['spatialType' => $type, 'srid' => $srid]);
    }

    /**
     * Create a PostGIS geometry column
     */
    public function geometry(string $name, string $type = 'POINT', int $srid = 4326): ColumnDefinition
    {
        return $this->addColumn('geometry', $name, ['spatialType' => $type, 'srid' => $srid]);
    }

    // ==================== Indexes ====================

    /**
     * Add an index
     */
    public function index(string|array $columns, ?string $name = null): IndexDefinition
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $index = new IndexDefinition('index', $columns, $name);
        $this->commands[] = ['type' => 'index', 'definition' => $index];
        return $index;
    }

    /**
     * Add a unique index
     */
    public function unique(string|array $columns, ?string $name = null): IndexDefinition
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $index = new IndexDefinition('unique', $columns, $name);
        $this->commands[] = ['type' => 'unique', 'definition' => $index];
        return $index;
    }

    /**
     * Add a primary key
     */
    public function primary(string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->commands[] = ['type' => 'primary', 'columns' => $columns, 'name' => $name];
    }

    /**
     * Add a spatial index (PostGIS)
     */
    public function spatialIndex(string|array $columns, ?string $name = null): IndexDefinition
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $index = new IndexDefinition('spatial', $columns, $name);
        $index->gist(); // Use GIST algorithm by default for spatial indexes
        $this->commands[] = ['type' => 'spatialIndex', 'definition' => $index];
        return $index;
    }

    /**
     * Add a fulltext index
     */
    public function fulltext(string|array $columns, ?string $name = null): IndexDefinition
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $index = new IndexDefinition('fulltext', $columns, $name);
        $this->commands[] = ['type' => 'fulltext', 'definition' => $index];
        return $index;
    }

    // ==================== Foreign Keys ====================

    /**
     * Add a foreign key constraint
     */
    public function foreign(string|array $columns): ForeignKeyDefinition
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $foreign = new ForeignKeyDefinition($this, $columns);
        $this->commands[] = ['type' => 'foreign', 'definition' => $foreign];
        return $foreign;
    }

    // ==================== Drop Operations ====================

    /**
     * Drop a column
     */
    public function dropColumn(string|array $columns): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->commands[] = ['type' => 'dropColumn', 'columns' => $columns];
    }

    /**
     * Drop an index
     */
    public function dropIndex(string|array $indexName): void
    {
        $this->commands[] = ['type' => 'dropIndex', 'name' => $indexName];
    }

    /**
     * Drop a unique constraint
     */
    public function dropUnique(string|array $indexName): void
    {
        $this->commands[] = ['type' => 'dropUnique', 'name' => $indexName];
    }

    /**
     * Drop a foreign key constraint
     */
    public function dropForeign(string|array $keyName): void
    {
        $this->commands[] = ['type' => 'dropForeign', 'name' => $keyName];
    }

    /**
     * Drop the primary key
     */
    public function dropPrimary(?string $indexName = null): void
    {
        $this->commands[] = ['type' => 'dropPrimary', 'name' => $indexName];
    }

    // ==================== Rename Operations ====================

    /**
     * Rename a column
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->commands[] = ['type' => 'renameColumn', 'from' => $from, 'to' => $to];
    }

    // ==================== Internal Methods ====================

    /**
     * Add a column to the blueprint
     */
    protected function addColumn(string $type, string $name, array $attributes = []): ColumnDefinition
    {
        $column = new ColumnDefinition($type, $name, $attributes);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Get all columns
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get all commands
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
