<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;

/**
 * MakeCommandCommand - Generate a new command class
 *
 * Creates a new command class file in Framework/Cli/Commands
 * or in a feature's Commands directory.
 */
class MakeCommandCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'make:command {name} {--feature=}';

    /**
     * Command description
     */
    protected string $description = 'Create a new command class';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $feature = $this->option('feature');

        // Ensure name ends with 'Command'
        if (!str_ends_with($name, 'Command')) {
            $name .= 'Command';
        }

        // Determine path and namespace
        if ($feature) {
            $directory = ROOT_PATH . "/Features/{$feature}/Commands";
            $namespace = "Features\\{$feature}\\Commands";
            $relativePath = "Features/{$feature}/Commands/{$name}.php";
        } else {
            $directory = ROOT_PATH . "/Framework/Cli/Commands";
            $namespace = "Framework\\Cli\\Commands";
            $relativePath = "Framework/Cli/Commands/{$name}.php";
        }

        $filePath = $directory . '/' . $name . '.php';

        // Check if file already exists
        if (file_exists($filePath)) {
            $this->error("Command already exists: {$relativePath}");
            return self::FAILURE;
        }

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $this->error("Failed to create directory: {$directory}");
                return self::FAILURE;
            }
        }

        // Generate command class content
        $content = $this->generateCommandClass($name, $namespace);

        // Write file
        if (file_put_contents($filePath, $content) === false) {
            $this->error("Failed to create command file: {$relativePath}");
            return self::FAILURE;
        }

        $this->success("Command created successfully!");
        $this->info("Location: {$relativePath}");

        // Derive command name
        $commandName = $this->deriveCommandName($name);
        $this->info("Command name: {$commandName}");

        return self::SUCCESS;
    }

    /**
     * Generate command class content
     *
     * @param string $className Class name
     * @param string $namespace Namespace
     * @return string Class content
     */
    private function generateCommandClass(string $className, string $namespace): string
    {
        $commandName = $this->deriveCommandName($className);

        return <<<PHP
<?php

namespace {$namespace};

use Framework\Cli\Command;

/**
 * {$className} - Command description
 *
 * TODO: Add detailed command description
 */
class {$className} extends Command
{
    /**
     * Command signature
     */
    protected string \$signature = '{$commandName} {--option=}';

    /**
     * Command description
     */
    protected string \$description = 'Command description goes here';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        \$this->info('Executing {$commandName}...');

        // TODO: Implement command logic

        \$this->success('Command completed successfully!');

        return self::SUCCESS;
    }
}

PHP;
    }

    /**
     * Derive command name from class name
     *
     * @param string $className Class name
     * @return string Command name
     */
    private function deriveCommandName(string $className): string
    {
        // Remove 'Command' suffix
        $name = preg_replace('/Command$/', '', $className);

        // Convert PascalCase to kebab:case
        $name = preg_replace('/([a-z])([A-Z])/', '$1:$2', $name);

        return strtolower($name);
    }
}
