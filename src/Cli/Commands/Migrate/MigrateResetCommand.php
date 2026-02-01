<?php

namespace Framework\Cli\Commands\Migrate;

use Framework\Cli\Command;
use Framework\Database\Migrations\MigrationManager;

/**
 * Migrate Reset Command
 *
 * Rollback all database migrations
 */
class MigrateResetCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'migrate:reset {--force}';

    /**
     * Command description
     */
    protected string $description = 'Rollback all database migrations';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $force = $this->option('force');

        $this->warning('This command will rollback all migrations!');
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
            $this->info('Rolling back all migrations...');

            $migration = MigrationManager::getInstance();
            $result = $migration->rollbackAll();

            if ($result['success']) {
                $this->success('All migrations rolled back successfully!');
                $this->newLine();

                if (!empty($result['rolled_back'])) {
                    $this->table(
                        ['Migration', 'Status'],
                        array_map(fn($m) => [$m, $this->output->getColor()->yellow('Rolled Back')], $result['rolled_back'])
                    );
                } else {
                    $this->info('No migrations to rollback.');
                }
            } else {
                $this->error('Rollback failed!');

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
            $this->error('Reset error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
