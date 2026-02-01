<?php

namespace Vireo\Framework\Cli\Commands;

use Vireo\Framework\Cli\Command;
use Vireo\Framework\Database\Migration;

/**
 * MigrateStatusCommand - Show migration status
 *
 * Displays the status of all database migrations.
 */
class MigrateStatusCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'migrate:status';

    /**
     * Command description
     */
    protected string $description = 'Show migration status';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $this->info('Database Migration Status');
        $this->newLine();

        try {
            $migration = Migration::getInstance();
            $status = $migration->status();

            if (empty($status)) {
                $this->warning('Could not retrieve migration status.');
                return self::FAILURE;
            }

            // Display summary
            $this->line("Total migrations: {$status['total']}");
            $this->line("Executed: " . $this->output->getColor()->green((string)$status['executed']));
            $this->line("Pending: " . $this->output->getColor()->yellow((string)$status['pending']));
            $this->newLine();

            if (!empty($status['migrations'])) {
                // Prepare table data
                $rows = [];
                foreach ($status['migrations'] as $mig) {
                    $statusText = $mig['status'] === 'executed'
                        ? $this->output->getColor()->green('Executed')
                        : $this->output->getColor()->yellow('Pending');

                    $rows[] = [$mig['migration'], $statusText];
                }

                $this->table(['Migration', 'Status'], $rows);
            } else {
                $this->info('No migrations found.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Status error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
