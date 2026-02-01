<?php

use Vireo\Framework\Database\ORM\QueryBuilder;
use Vireo\Framework\Database\ORM\SpatialQueryBuilder;
use Vireo\Framework\Database\ORM\Collection;

if (!function_exists('query')) {
    /**
     * Create a new query builder instance
     *
     * @param string|null $connection Connection name
     * @return QueryBuilder
     */
    function query(?string $connection = null): QueryBuilder
    {
        return new QueryBuilder($connection);
    }
}

if (!function_exists('table')) {
    /**
     * Create a query builder for a specific table
     *
     * @param string $table Table name
     * @param string|null $connection Connection name
     * @return QueryBuilder
     */
    function table(string $table, ?string $connection = null): QueryBuilder
    {
        return (new QueryBuilder($connection))->table($table);
    }
}

if (!function_exists('spatial')) {
    /**
     * Create a new spatial query builder instance with PostGIS support
     *
     * @param string|null $table Table name
     * @param string|null $connection Connection name
     * @return SpatialQueryBuilder
     */
    function spatial(?string $table = null, ?string $connection = null): SpatialQueryBuilder
    {
        $builder = new SpatialQueryBuilder($connection);

        if ($table !== null) {
            $builder->table($table);
        }

        return $builder;
    }
}

if (!function_exists('collect')) {
    /**
     * Create a new collection instance
     *
     * @param array $items Items to collect
     * @return Collection
     */
    function collect(array $items = []): Collection
    {
        return new Collection($items);
    }
}
