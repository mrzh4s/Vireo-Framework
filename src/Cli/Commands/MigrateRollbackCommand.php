<?php

namespace Vireo\Framework\Cli\Commands;

use Vireo\Framework\Cli\Command;
use Vireo\Framework\Database\Migration;

/**
 * MigrateRollbackCommand - Rollback database migrations
 *
 * Rolls back the last batch of database migrations.
 */
class MigrateRollbackCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'migrate:rollback {--force}';

    /**
     * Command description
     */
    protected string $description = 'Rollback the last batch of migrations';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $force = $this->option('force');

        $this->warning('Rolling back migrations...');
        $this->newLine();

        // Confirm if not forced
        if (!$force) {
            if (!$this->confirm('Are you sure you want to rollback migrations?', false)) {
                $this->info('Rollback cancelled.');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        try {
            $migration = Migration::getInstance();
            $result = $migration->rollback();

            if ($result['success']) {
                if (isset($result['rolled_back']) && !empty($result['rolled_back'])) {
                    $this->success('Migrations rolled back successfully!');
                    $this->newLine();

                    // Display rolled back files
                    $this->table(
                        ['Migration', 'Status'],
                        array_map(
                            fn($m) => [$m, $this->output->getColor()->yellow('Rolled back')],
                            $result['rolled_back']
                        )
                    );

                    $this->newLine();
                    $this->info($result['message']);
                } else {
                    $this->info($result['message'] ?? 'Nothing to rollback.');
                }
            } else {
                $this->error('Rollback failed!');
                if (isset($result['error'])) {
                    $this->error($result['error']);
                }
                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Rollback error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
