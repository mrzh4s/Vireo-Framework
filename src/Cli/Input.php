<?php

namespace Vireo\Framework\Cli;

/**
 * Input - Command-line argument parser
 *
 * Parses argv into command name, positional arguments, and options/flags.
 * Supports role syntax (@admin) for special command modifiers.
 */
class Input
{
    /**
     * The command name
     */
    private string $command;

    /**
     * Positional arguments
     *
     * @var array<int, string>
     */
    private array $arguments = [];

    /**
     * Options and flags
     *
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * Raw argv
     *
     * @var array<int, string>
     */
    private array $raw;

    /**
     * Create Input instance from argv
     *
     * @param array<int, string> $argv Raw command-line arguments
     */
    public function __construct(array $argv)
    {
        $this->raw = $argv;
        $this->parse($argv);
    }

    /**
     * Parse argv into command, arguments, and options
     *
     * @param array<int, string> $argv Raw arguments
     * @return void
     */
    private function parse(array $argv): void
    {
        // First element is the script name, second is the command
        $script = array_shift($argv);
        $commandName = array_shift($argv) ?? 'list';

        // Handle role syntax: create:user@admin → command='create:user', option['role']='admin'
        if (strpos($commandName, '@') !== false) {
            [$commandName, $roleValue] = explode('@', $commandName, 2);
            $this->options['role'] = $roleValue;
        }

        $this->command = $commandName;

        // Parse remaining arguments and options
        foreach ($argv as $arg) {
            if ($this->isOption($arg)) {
                $this->parseOption($arg);
            } else {
                $this->arguments[] = $arg;
            }
        }
    }

    /**
     * Check if argument is an option (starts with - or --)
     *
     * @param string $arg Argument string
     * @return bool True if option
     */
    private function isOption(string $arg): bool
    {
        return str_starts_with($arg, '-');
    }

    /**
     * Parse an option argument
     *
     * @param string $arg Option string
     * @return void
     */
    private function parseOption(string $arg): void
    {
        // Remove leading dashes
        $arg = ltrim($arg, '-');

        // Check for value assignment: --option=value
        if (str_contains($arg, '=')) {
            [$name, $value] = explode('=', $arg, 2);
            $this->options[$name] = $value;
        } else {
            // Boolean flag: --flag
            $this->options[$arg] = true;
        }
    }

    /**
     * Get the command name
     *
     * @return string Command name
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get a positional argument by index
     *
     * @param int $index Argument index (0-based)
     * @return string|null Argument value or null if not found
     */
    public function getArgumentByIndex(int $index): ?string
    {
        return $this->arguments[$index] ?? null;
    }

    /**
     * Get a named argument (by signature definition)
     *
     * @param string $name Argument name
     * @param array<int, string> $signatureArguments Signature argument names
     * @return mixed Argument value or null if not found
     */
    public function getArgument(string $name, array $signatureArguments = []): mixed
    {
        // Find the index of the argument name in signature
        $index = array_search($name, $signatureArguments, true);
        if ($index === false) {
            return null;
        }

        return $this->getArgumentByIndex($index);
    }

    /**
     * Get an option by name
     *
     * @param string $name Option name
     * @param mixed $default Default value if not found
     * @return mixed Option value or default
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Check if an option is set
     *
     * @param string $name Option name
     * @return bool True if option is set
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Get all positional arguments
     *
     * @return array<int, string> All arguments
     */
    public function getAllArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get all options
     *
     * @return array<string, mixed> All options
     */
    public function getAllOptions(): array
    {
        return $this->options;
    }

    /**
     * Check if argument exists at index
     *
     * @param int $index Argument index
     * @return bool True if argument exists
     */
    public function hasArgument(int $index): bool
    {
        return isset($this->arguments[$index]);
    }

    /**
     * Get count of positional arguments
     *
     * @return int Number of arguments
     */
    public function getArgumentCount(): int
    {
        return count($this->arguments);
    }

    /**
     * Get raw argv
     *
     * @return array<int, string> Raw arguments
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
