<?php

namespace Vireo\Framework\Cli;

/**
 * ArgumentParser - Command signature parser and validator
 *
 * Parses command signatures into structured definitions,
 * validates input against signatures, and applies defaults.
 */
class ArgumentParser
{
    /**
     * Parsed command name
     */
    private string $commandName = '';

    /**
     * Parsed arguments
     *
     * @var array<int, array{name: string, optional: bool, default: mixed}>
     */
    private array $arguments = [];

    /**
     * Parsed options
     *
     * @var array<string, array{name: string, acceptValue: bool, default: mixed}>
     */
    private array $options = [];

    /**
     * Role parameter name (from @{param} syntax)
     */
    private ?string $roleParameter = null;

    /**
     * Parse a command signature
     *
     * Signature format:
     * command:name@{role=user} {arg1} {arg2?} {arg3=default} {--option} {--flag=}
     *
     * @param string $signature Command signature
     * @return self
     */
    public function parseSignature(string $signature): self
    {
        if (empty($signature)) {
            return $this;
        }

        // Extract command name (first word)
        if (preg_match('/^([^\s{]+)/', $signature, $commandMatch)) {
            $commandName = $commandMatch[1];

            // Check for role parameter: command@{role=default}
            if (preg_match('/^([^@]+)@\{([^}=]+)=?([^}]*)\}/', $commandName, $roleMatch)) {
                $this->commandName = $roleMatch[1];
                $this->roleParameter = $roleMatch[2];

                // Register role as an option
                $this->options[$roleMatch[2]] = [
                    'name' => $roleMatch[2],
                    'acceptValue' => true,
                    'default' => $roleMatch[3] ?: null,
                ];
            } else {
                $this->commandName = $commandName;
            }
        }

        // Extract all parameters in braces: {arg}, {--option}
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);

        foreach ($matches[1] as $match) {
            if (str_starts_with($match, '--')) {
                $this->parseOption($match);
            } elseif (!str_starts_with($match, '@')) {
                // Skip @{role} since we already handled it
                $this->parseArgument($match);
            }
        }

        return $this;
    }

    /**
     * Parse an argument definition
     *
     * @param string $arg Argument string (e.g., 'name', 'name?', 'name=default')
     * @return void
     */
    private function parseArgument(string $arg): void
    {
        $optional = false;
        $default = null;

        // Check for optional marker: name?
        if (str_ends_with($arg, '?')) {
            $optional = true;
            $arg = rtrim($arg, '?');
        }

        // Check for default value: name=default
        if (str_contains($arg, '=')) {
            [$arg, $default] = explode('=', $arg, 2);
            $optional = true;
        }

        $this->arguments[] = [
            'name' => $arg,
            'optional' => $optional,
            'default' => $default,
        ];
    }

    /**
     * Parse an option definition
     *
     * @param string $opt Option string (e.g., '--flag', '--option=', '--option=default')
     * @return void
     */
    private function parseOption(string $opt): void
    {
        // Remove leading dashes
        $opt = ltrim($opt, '-');

        $acceptValue = false;
        $default = null;

        // Check for value acceptance: option=
        if (str_ends_with($opt, '=')) {
            $acceptValue = true;
            $opt = rtrim($opt, '=');
        }

        // Check for default value: option=default
        if (str_contains($opt, '=')) {
            [$opt, $default] = explode('=', $opt, 2);
            $acceptValue = true;
        }

        $this->options[$opt] = [
            'name' => $opt,
            'acceptValue' => $acceptValue,
            'default' => $default,
        ];
    }

    /**
     * Validate input against signature
     *
     * @param Input $input Input instance
     * @return array<string> Validation errors
     */
    public function validate(Input $input): array
    {
        $errors = [];

        // Validate required arguments
        foreach ($this->arguments as $index => $argument) {
            if (!$argument['optional'] && !$input->hasArgument($index)) {
                $errors[] = "Missing required argument: {$argument['name']}";
            }
        }

        // Check for excess arguments
        $providedCount = $input->getArgumentCount();
        $expectedCount = count($this->arguments);

        if ($providedCount > $expectedCount) {
            $excess = $providedCount - $expectedCount;
            $errors[] = "Too many arguments provided ({$excess} extra)";
        }

        return $errors;
    }

    /**
     * Get argument definitions
     *
     * @return array<int, array{name: string, optional: bool, default: mixed}>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get option definitions
     *
     * @return array<string, array{name: string, acceptValue: bool, default: mixed}>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get command name
     *
     * @return string Command name
     */
    public function getCommandName(): string
    {
        return $this->commandName;
    }

    /**
     * Get role parameter name
     *
     * @return string|null Role parameter name
     */
    public function getRoleParameter(): ?string
    {
        return $this->roleParameter;
    }

    /**
     * Get argument names only
     *
     * @return array<int, string> Argument names
     */
    public function getArgumentNames(): array
    {
        return array_map(fn($arg) => $arg['name'], $this->arguments);
    }

    /**
     * Get option names only
     *
     * @return array<int, string> Option names
     */
    public function getOptionNames(): array
    {
        return array_keys($this->options);
    }

    /**
     * Generate help text from signature
     *
     * @return string Help text
     */
    public function generateHelp(): string
    {
        $parts = [$this->commandName];

        // Add arguments
        foreach ($this->arguments as $arg) {
            if ($arg['optional']) {
                $defaultText = $arg['default'] !== null ? "={$arg['default']}" : '';
                $parts[] = "[{$arg['name']}{$defaultText}]";
            } else {
                $parts[] = "<{$arg['name']}>";
            }
        }

        // Add options
        foreach ($this->options as $opt) {
            if ($opt['acceptValue']) {
                $defaultText = $opt['default'] !== null ? "={$opt['default']}" : '=VALUE';
                $parts[] = "[--{$opt['name']}{$defaultText}]";
            } else {
                $parts[] = "[--{$opt['name']}]";
            }
        }

        return implode(' ', $parts);
    }
}
