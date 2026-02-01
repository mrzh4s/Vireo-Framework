<?php

namespace Framework\Cli\Commands\Make;

use Framework\Cli\Command;
use Framework\Database\Migrations\MigrationCreator;

/**
 * Make Migration Command
 *
 * Generates a new migration file
 */
class MakeMigrationCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'make:migration {name} {--create=} {--table=}';

    /**
     * Command description
     */
    protected string $description = 'Create a new migration file';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $table = $this->option('table');
        $create = $this->option('create');

        // Determine if we're creating or updating a table
        $isCreate = $create !== null;
        $tableName = $create ?? $table;

        try {
            $creator = new MigrationCreator();
            $result = $creator->create($name, $tableName, $isCreate);

            if (!$result['success']) {
                $this->error($result['error']);
                return self::FAILURE;
            }

            $this->success('Migration created successfully!');
            $this->newLine();
            $this->info('File: ' . $result['file']);
            $this->info('Path: ' . $result['path']);
            $this->info('Class: ' . $result['class']);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to create migration: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
