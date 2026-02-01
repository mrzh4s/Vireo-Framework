<?php

namespace Vireo\Framework\Cli\Commands\Migrate;

use Vireo\Framework\Cli\Command;
use Vireo\Framework\Database\Migrations\MigrationManager;

/**
 * Migrate Refresh Command
 *
 * Rollback all migrations and re-run them
 */
class MigrateRefreshCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'migrate:refresh {--force}';

    /**
     * Command description
     */
    protected string $description = 'Rollback all migrations and re-run them';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $force = $this->option('force');

        $this->warning('This command will rollback all migrations and re-run them!');
        $this->newLine();

        // Confirm if not forced
        if (!$force) {
            if (!$this->confirm('Are you sure?', false)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        try {
            $migration = MigrationManager::getInstance();

            // Rollback all
            $this->info('Rolling back all migrations...');
            $rollbackResult = $migration->rollbackAll();

            if (!$rollbackResult['success']) {
                $this->error('Rollback failed!');
                return self::FAILURE;
            }

            if (!empty($rollbackResult['rolled_back'])) {
                $this->success('Rolled back ' . count($rollbackResult['rolled_back']) . ' migrations');
                $this->newLine();
            }

            // Re-run all
            $this->info('Running migrations...');
            $migrateResult = $migration->run();

            if ($migrateResult['success']) {
                $this->success('Migration refresh completed successfully!');
                $this->newLine();

                if (!empty($migrateResult['migrated'])) {
                    $this->table(
                        ['Migration', 'Status'],
                        array_map(fn($m) => [$m, $this->output->getColor()->green('Migrated')], $migrateResult['migrated'])
                    );
                } else {
                    $this->info('No migrations to run.');
                }
            } else {
                $this->error('Migration failed!');

                if (isset($migrateResult['errors'])) {
                    $this->newLine();
                    foreach ($migrateResult['errors'] as $error) {
                        $this->error("  {$error['migration']}: {$error['error']}");
                    }
                }

                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Refresh error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
