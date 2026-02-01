<?php

use Vireo\Framework\Database\Migrations\Schema;

if (!function_exists('schema')) {
    /**
     * Get a schema builder instance
     *
     * @param string|null $connection Connection name
     * @return Schema
     */
    function schema(?string $connection = null): Schema
    {
        if ($connection !== null) {
            return Schema::connection($connection);
        }

        return new Schema();
    }
}
