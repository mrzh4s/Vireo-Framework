<?php

namespace Vireo\Framework\Cli\Commands;

use Vireo\Framework\Cli\Command;
use Vireo\Framework\Database\Migration;

/**
 * MigrateCommand - Run database migrations
 *
 * Executes all pending database migrations.
 */
class MigrateCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'migrate {--force} {--silent}';

    /**
     * Command description
     */
    protected string $description = 'Run database migrations';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $silent = $this->option('silent');

        if (!$silent) {
            $this->info('Running database migrations...');
            $this->newLine();
        }

        // Confirm if not forced
        if (!$force && !$silent) {
            if (!$this->confirm('Do you want to run migrations?', true)) {
                $this->info('Migration cancelled.');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        try {
            $migration = Migration::getInstance();
            $result = $migration->run();

            if (!$silent) {
                if ($result['success']) {
                    if (empty($result['migrated'])) {
                        $this->info('No pending migrations to run.');
                    } else {
                        $this->success('Migrations completed successfully!');
                        $this->newLine();

                        // Display migrated files
                        $this->table(
                            ['Migration', 'Status'],
                            array_map(fn($m) => [$m, $this->output->getColor()->green('Migrated')], $result['migrated'])
                        );
                    }
                } else {
                    $this->error('Migration failed!');

                    if (isset($result['errors']) && !empty($result['errors'])) {
                        $this->newLine();
                        foreach ($result['errors'] as $error) {
                            $this->error("  {$error['migration']}: {$error['error']}");
                        }
                    }

                    return self::FAILURE;
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            if (!$silent) {
                $this->error('Migration error: ' . $e->getMessage());
            }
            return self::FAILURE;
        }
    }
}
