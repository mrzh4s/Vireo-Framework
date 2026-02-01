<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;

/**
 * InspectCommand - Inspect framework information
 *
 * Displays information about the framework, PHP, and environment.
 */
class InspectCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'inspect {--verbose}';

    /**
     * Command description
     */
    protected string $description = 'Inspect framework information';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $this->info('Vireo Framework - System Information');
        $this->newLine();

        // PHP Information
        $this->line($this->output->getColor()->bold('PHP:'));
        $this->table(
            ['Setting', 'Value'],
            [
                ['PHP Version', PHP_VERSION],
                ['PHP SAPI', PHP_SAPI],
                ['OS', PHP_OS],
                ['Architecture', php_uname('m')],
            ]
        );
        $this->newLine();

        // Framework Information
        $this->line($this->output->getColor()->bold('Framework:'));
        $this->table(
            ['Setting', 'Value'],
            [
                ['Root Path', ROOT_PATH],
                ['Environment', env('APP_ENV', 'production')],
                ['Debug Mode', env('APP_DEBUG', 'false')],
                ['Timezone', date_default_timezone_get()],
            ]
        );
        $this->newLine();

        // Database Information
        if (function_exists('config')) {
            $this->line($this->output->getColor()->bold('Database:'));
            $dbDefault = env('DB_DEFAULT', 'main');
            $dbDriver = config("database.connections.{$dbDefault}.driver", 'unknown');
            $dbHost = config("database.connections.{$dbDefault}.host", 'N/A');
            $dbDatabase = config("database.connections.{$dbDefault}.database", 'N/A');

            $this->table(
                ['Setting', 'Value'],
                [
                    ['Default Connection', $dbDefault],
                    ['Driver', $dbDriver],
                    ['Host', $dbHost],
                    ['Database', $dbDatabase],
                ]
            );
            $this->newLine();
        }

        // Verbose information
        if ($this->option('verbose')) {
            $this->line($this->output->getColor()->bold('PHP Extensions:'));
            $extensions = get_loaded_extensions();
            sort($extensions);
            $chunks = array_chunk($extensions, 4);
            foreach ($chunks as $chunk) {
                $this->line('  ' . implode(', ', $chunk));
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
