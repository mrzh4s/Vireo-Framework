<?php

namespace Framework\Cli\Commands\Migrate;

use Framework\Cli\Command;
use Framework\Database\Migrations\MigrationManager;

/**
 * Migrate Fresh Command
 *
 * Drop all tables and re-run all migrations
 */
class MigrateFreshCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'migrate:fresh {--force}';

    /**
     * Command description
     */
    protected string $description = 'Drop all tables and re-run all migrations';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $force = $this->option('force');

        $this->warning('This command will drop all tables!');
        $this->newLine();

        // Confirm if not forced
        if (!$force) {
            if (!$this->confirm('Are you sure you want to drop all tables and re-migrate?', false)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        try {
            $this->info('Dropping all tables...');

            $migration = MigrationManager::getInstance();
            $result = $migration->fresh();

            if ($result['success']) {
                $this->success('Fresh migration completed successfully!');
                $this->newLine();

                if (!empty($result['migrated'])) {
                    $this->table(
                        ['Migration', 'Status'],
                        array_map(fn($m) => [$m, $this->output->getColor()->green('Migrated')], $result['migrated'])
                    );
                } else {
                    $this->info('No migrations to run.');
                }
            } else {
                $this->error('Fresh migration failed!');

                if (isset($result['errors'])) {
                    $this->newLine();
                    foreach ($result['errors'] as $error) {
                        if (is_array($error)) {
                            $this->error("  {$error['migration']}: {$error['error']}");
                        } else {
                            $this->error("  {$error}");
                        }
                    }
                }

                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Fresh migration error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
