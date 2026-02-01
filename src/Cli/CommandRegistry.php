<?php

namespace Framework\Cli;

/**
 * CommandRegistry - Manages command registration and lookup
 *
 * Maintains a registry of all available commands with their class names,
 * descriptions, and aliases. Provides command discovery and lookup functionality.
 */
class CommandRegistry
{
    /**
     * Registered commands
     * Format: ['command:name' => ['class' => 'FullClassName', 'description' => 'Description']]
     *
     * @var array<string, array{class: string, description: string}>
     */
    private array $commands = [];

    /**
     * Command aliases
     * Format: ['alias' => 'command:name']
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Register a new command
     *
     * @param string $name Command name (e.g., 'serve', 'make:command')
     * @param string $class Fully qualified class name
     * @param string|null $description Command description
     * @return void
     */
    public function register(string $name, string $class, ?string $description = null): void
    {
        $this->commands[$name] = [
            'class' => $class,
            'description' => $description ?? '',
        ];
    }

    /**
     * Get command class by name
     *
     * @param string $name Command name or alias
     * @return string|null Fully qualified class name or null if not found
     */
    public function get(string $name): ?string
    {
        // Check if it's an alias first
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        return $this->commands[$name]['class'] ?? null;
    }

    /**
     * Get command information by name
     *
     * @param string $name Command name
     * @return array{class: string, description: string}|null Command info or null if not found
     */
    public function getInfo(string $name): ?array
    {
        // Check if it's an alias first
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        return $this->commands[$name] ?? null;
    }

    /**
     * Check if command exists
     *
     * @param string $name Command name or alias
     * @return bool True if command exists
     */
    public function has(string $name): bool
    {
        return isset($this->aliases[$name]) || isset($this->commands[$name]);
    }

    /**
     * Get all registered commands
     *
     * @return array<string, array{class: string, description: string}> All commands with their info
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * Get all command names
     *
     * @return array<string> Array of command names
     */
    public function names(): array
    {
        return array_keys($this->commands);
    }

    /**
     * Register a command alias
     *
     * @param string $alias Alias name
     * @param string $command Actual command name
     * @return void
     */
    public function alias(string $alias, string $command): void
    {
        $this->aliases[$alias] = $command;
    }

    /**
     * Get all registered aliases
     *
     * @return array<string, string> All aliases
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Count total registered commands
     *
     * @return int Number of registered commands
     */
    public function count(): int
    {
        return count($this->commands);
    }
}
