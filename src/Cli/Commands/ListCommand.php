<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;
use Framework\Cli\Console;

/**
 * ListCommand - Display all available commands
 *
 * Lists all registered commands with their descriptions in a formatted table.
 */
class ListCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'list {--raw}';

    /**
     * Command description
     */
    protected string $description = 'List all available commands';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $console = Console::getInstance();
        $registry = $console->getRegistry();
        $commands = $registry->all();

        if (empty($commands)) {
            $this->warning('No commands registered.');
            return self::SUCCESS;
        }

        // Raw output (just command names)
        if ($this->option('raw')) {
            foreach ($commands as $name => $info) {
                $this->line($name);
            }
            return self::SUCCESS;
        }

        // Formatted output
        $this->info('Vireo Framework - Available Commands');
        $this->newLine();

        // Group commands by namespace
        $grouped = $this->groupCommands($commands);

        foreach ($grouped as $namespace => $namespaceCommands) {
            if ($namespace) {
                $this->line($this->output->getColor()->bold($namespace . ':'));
            } else {
                $this->line($this->output->getColor()->bold('General:'));
            }

            $rows = [];
            foreach ($namespaceCommands as $name => $info) {
                $rows[] = ['  ' . $name, $info['description']];
            }

            $this->table(['Command', 'Description'], $rows);
            $this->newLine();
        }

        $this->info('Run "vireo <command> --help" for more information on a command.');

        return self::SUCCESS;
    }

    /**
     * Group commands by namespace
     *
     * @param array<string, array{class: string, description: string}> $commands All commands
     * @return array<string, array<string, array{class: string, description: string}>> Grouped commands
     */
    private function groupCommands(array $commands): array
    {
        $grouped = [];

        foreach ($commands as $name => $info) {
            $parts = explode(':', $name);

            if (count($parts) > 1) {
                // Has namespace (e.g., 'make:command')
                $namespace = $parts[0];
                if (!isset($grouped[$namespace])) {
                    $grouped[$namespace] = [];
                }
                $grouped[$namespace][$name] = $info;
            } else {
                // No namespace (e.g., 'serve', 'list')
                if (!isset($grouped[''])) {
                    $grouped[''] = [];
                }
                $grouped[''][$name] = $info;
            }
        }

        // Sort groups
        ksort($grouped);

        return $grouped;
    }
}
