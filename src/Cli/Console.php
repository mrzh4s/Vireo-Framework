<?php

namespace Framework\Cli;

use Exception;

/**
 * Console - CLI kernel and command orchestrator
 *
 * Main entry point for CLI system. Auto-discovers commands, handles routing,
 * exception handling, and exit codes. Follows singleton pattern like Router.
 */
class Console
{
    /**
     * Singleton instance
     */
    private static ?Console $instance = null;

    /**
     * Command registry
     */
    private CommandRegistry $registry;

    /**
     * Output instance
     */
    private Output $output;

    /**
     * Whether commands have been discovered
     */
    private bool $commandsDiscovered = false;

    /**
     * Private constructor (Singleton)
     */
    private function __construct()
    {
        $this->registry = new CommandRegistry();
        $this->output = new Output();
    }

    /**
     * Get Console singleton instance
     *
     * @return Console Console instance
     */
    public static function getInstance(): Console
    {
        if (self::$instance === null) {
            self::$instance = new Console();
        }

        return self::$instance;
    }

    /**
     * Run console application
     *
     * @param array<int, string> $argv Command-line arguments
     * @return int Exit code
     */
    public function run(array $argv): int
    {
        try {
            // Discover commands if not already done
            if (!$this->commandsDiscovered) {
                $this->discoverCommands();
            }

            // Parse input
            $input = new Input($argv);
            $commandName = $input->getCommand();

            // Get command class
            $commandClass = $this->registry->get($commandName);

            if ($commandClass === null) {
                $this->output->error("Command not found: {$commandName}");
                $this->output->info("Run 'vireo list' to see available commands.");
                return Command::INVALID;
            }

            // Instantiate and run command
            $command = new $commandClass();

            if (!($command instanceof Command)) {
                throw new Exception("Command class must extend Framework\\Cli\\Command");
            }

            // Inject dependencies
            $command->setInput($input);
            $command->setOutput($this->output);
            $command->setQuestion(new Question($this->output));

            // Run command
            return $command->run();

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Auto-discover commands from Framework and Features
     *
     * @return void
     */
    private function discoverCommands(): void
    {
        // 1. Load built-in commands from Framework/Cli/Commands
        $builtInPath = ROOT_PATH . '/Framework/Cli/Commands';
        $this->loadCommandsFromDirectory($builtInPath, 'Framework\\Cli\\Commands');

        // 2. Load feature commands from Features/*/Commands
        $featuresPath = ROOT_PATH . '/Features';
        if (is_dir($featuresPath)) {
            $features = scandir($featuresPath);
            foreach ($features as $feature) {
                if ($feature === '.' || $feature === '..') {
                    continue;
                }

                $commandsPath = $featuresPath . '/' . $feature . '/Commands';
                $namespace = 'Features\\' . $feature . '\\Commands';
                $this->loadCommandsFromDirectory($commandsPath, $namespace);
            }
        }

        $this->commandsDiscovered = true;
    }

    /**
     * Load commands from a directory
     *
     * @param string $directory Directory path
     * @param string $namespace Namespace for classes
     * @return void
     */
    private function loadCommandsFromDirectory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*Command.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fullClassName = $namespace . '\\' . $className;

            if (class_exists($fullClassName)) {
                $commandName = $this->deriveCommandName($className);

                // Instantiate to get description
                try {
                    $command = new $fullClassName();
                    if ($command instanceof Command) {
                        $description = $command->getDescription();
                        $this->registry->register($commandName, $fullClassName, $description);
                    }
                } catch (Exception $e) {
                    // Skip commands that can't be instantiated
                    continue;
                }
            }
        }
    }

    /**
     * Derive command name from class name
     *
     * Converts PascalCase to kebab:case with colons
     * CreateUserCommand → create:user
     * ServeCommand → serve
     * MakeFeatureCommand → make:feature
     *
     * @param string $className Class name
     * @return string Command name
     */
    private function deriveCommandName(string $className): string
    {
        // Remove 'Command' suffix
        $name = preg_replace('/Command$/', '', $className);

        // Convert PascalCase to kebab:case
        // Insert colon before uppercase letters (except the first)
        $name = preg_replace('/([a-z])([A-Z])/', '$1:$2', $name);

        // Convert to lowercase
        return strtolower($name);
    }

    /**
     * Handle exceptions
     *
     * @param Exception $e Exception
     * @return int Exit code
     */
    private function handleException(Exception $e): int
    {
        $this->output->error($e->getMessage());

        // Show stack trace if debug mode is enabled
        if (function_exists('config') && config('app.debug', false)) {
            $this->output->newLine();
            $this->output->writeln('Exception: ' . get_class($e));
            $this->output->writeln('File: ' . $e->getFile() . ':' . $e->getLine());
            $this->output->newLine();
            $this->output->writeln('Stack trace:');
            $this->output->writeln($e->getTraceAsString());
        }

        return Command::FAILURE;
    }

    /**
     * Register a command manually
     *
     * @param string $name Command name
     * @param string $class Command class
     * @param string|null $description Command description
     * @return void
     */
    public function register(string $name, string $class, ?string $description = null): void
    {
        $this->registry->register($name, $class, $description);
    }

    /**
     * Get the command registry
     *
     * @return CommandRegistry Registry instance
     */
    public function getRegistry(): CommandRegistry
    {
        return $this->registry;
    }

    /**
     * Get the output instance
     *
     * @return Output Output instance
     */
    public function getOutput(): Output
    {
        return $this->output;
    }
}
