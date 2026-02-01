<?php

namespace Framework\Database\Migrations;

/**
 * Index Definition
 *
 * Defines database indexes with their properties
 */
class IndexDefinition
{
    /**
     * Index type (index, unique, primary, spatial, fulltext)
     */
    public string $type;

    /**
     * Column names
     */
    public array $columns;

    /**
     * Index name
     */
    public ?string $name;

    /**
     * Index algorithm (btree, hash, gist, gin, etc.)
     */
    public ?string $algorithm = null;

    /**
     * Create a new index definition
     */
    public function __construct(string $type, array $columns, ?string $name = null)
    {
        $this->type = $type;
        $this->columns = $columns;
        $this->name = $name;
    }

    /**
     * Set the index algorithm
     */
    public function algorithm(string $algorithm): static
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    /**
     * Use GIST algorithm (PostgreSQL spatial indexes)
     */
    public function gist(): static
    {
        return $this->algorithm('gist');
    }

    /**
     * Use GIN algorithm (PostgreSQL - good for arrays, JSONB)
     */
    public function gin(): static
    {
        return $this->algorithm('gin');
    }

    /**
     * Use BTREE algorithm (default for most databases)
     */
    public function btree(): static
    {
        return $this->algorithm('btree');
    }

    /**
     * Use HASH algorithm
     */
    public function hash(): static
    {
        return $this->algorithm('hash');
    }
}
